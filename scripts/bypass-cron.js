const puppeteer = require('puppeteer');

(async () => {
  console.log('Starting headless Chrome to bypass anti-bot...');

  const browser = await puppeteer.launch({
    headless: 'new',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-blink-features=AutomationControlled',
      '--disable-web-security',
    ],
  });

  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0');

  page.on('console', msg => {
    if (msg.type() === 'error') console.log('Browser error:', msg.text());
  });

  page.on('response', resp => {
    if (resp.status() === 200 && resp.headers()['content-type'] && resp.headers()['content-type'].includes('json')) {
      console.log('Got JSON response from server!');
    }
  });

  try {
    console.log('Navigating to CRON_URL...');
    const response = await page.goto(process.env.CRON_URL, {
      waitUntil: 'networkidle0',
      timeout: 45000,
    });

    const status = response.status();
    const body = await response.text();
    const ct = response.headers()['content-type'] || '';

    console.log('HTTP Status:', status);
    console.log('Content-Type:', ct);
    console.log('Response body:', body.substring(0, 800));

    if (status === 200 && body.includes('success')) {
      console.log('SUCCESS: Cron triggered!');
      await browser.close();
      process.exit(0);
    } else if (body.includes('aes.js') || body.includes('<html')) {
      console.log('Anti-bot challenge detected. Waiting for JS execution...');
      await new Promise(r => setTimeout(r, 10000));

      const finalContent = await page.content();
      const finalText = await page.evaluate(() => {
        try { return document.body.innerText || document.body.textContent || ''; }
        catch { return ''; }
      });

      const finalBody = await response.text();

      console.log('Final content (browser text):', finalText.substring(0, 500));
      console.log('Final URL:', page.url());

      if (finalText.includes('success') || finalContent.includes('success')) {
        console.log('SUCCESS: Cron triggered after anti-bot bypass!');
        await browser.close();
        process.exit(0);
      } else {
        console.log('WARNING: Anti-bot challenge not solved. Page still contains challenge HTML.');
        // Try reload
        console.log('Attempting page reload...');
        try {
          const resp2 = await page.reload({ waitUntil: 'networkidle0', timeout: 30000 });
          const body2 = await resp2.text();
          console.log('Retry HTTP:', resp2.status());
          console.log('Retry body:', body2.substring(0, 500));
          if (body2.includes('success')) {
            console.log('SUCCESS: Cron triggered on retry!');
            await browser.close();
            process.exit(0);
          }
        } catch (e) {
          console.log('Retry error:', e.message);
        }
        await browser.close();
        process.exit(1);
      }
    } else {
      console.log('ERROR: Unexpected response format');
      await browser.close();
      process.exit(1);
    }
  } catch (e) {
    console.log('ERROR:', e.message);
    await browser.close();
    process.exit(1);
  }
})();
