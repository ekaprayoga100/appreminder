# UI/UX Design - App Reminder

## Design System

### Color Palette
```css
--primary: #6366f1       /* Indigo - primary actions */
--primary-hover: #4f46e5
--success: #10b981       /* Green - sent/done */
--warning: #f59e0b       /* Amber - today/overdue */
--danger: #ef4444        /* Red - failed/error */
--info: #06b6d4          /* Cyan - upcoming/scheduled */
--bg: #f1f5f9            /* Light gray - page bg */
--surface: #ffffff       /* White - card bg */
--ink: #0f172a           /* Dark - text */
--muted: #64748b         /* Gray - secondary text */
--line: #e2e8f0          /* Border color */
```

### Typography
| Element | Font Size | Weight | Color |
|---|---|---|---|
| Page titles | 22px | 700 | #0f172a |
| Card titles | 16px | 700 | #0f172a |
| Body text | 14px | 400 | #1f2937 |
| Labels | 13px | 600 | #334155 |
| Hint text | 12px | 400 | #64748b |

### Layout Structure
- **Sidebar**: Fixed 260px width (dark theme), collapsible on mobile
- **Topbar**: 64px height, user info + realtime clock
- **Main content**: Responsive grid

## Key Screens

### 1. Login Page
```
┌──────────────────────────────────┐
│  ┌────────────────────────┐    │
│  │   App Reminder Logo     │    │
│  │   PT Mayora Indah       │    │
│  │                          │    │
│  │   [Username       ]      │    │
│  │   [Password       ]      │    │
│  │                          │    │
│  │   [     Login       ]    │    │
│  │   ───────────────        │    │
│  │   [Daftar Akun Baru]     │    │
│  └────────────────────────┘    │
└──────────────────────────────────┘
```

### 2. Dashboard
```
[ Stat Card 1 ] [ Stat Card 2 ] [ Stat Card 3 ] [ Stat Card 4 ]
┌─────────────────────────────────────────────────────────┐
│  Search [________________] [Channel: All▼]              │
│                                                          │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ ID 12  Subject          Dept     Time   Status   │ │
│  │ ──────────────────────────────────────────        │ │
│  │ ID 13  Subject          Dept     Time   Status   │ │
│  │ ──────────────────────────────────────────        │ │
│  │ ...                                              │ │
│  └─────────────────────────────────────────────────────┘ │
│  [Bulk Delete] [Kirim Ulang] [Filter Buttons]           │
└─────────────────────────────────────────────────────────┘
```

### 3. Create/Edit Reminder
```
┌─────────────────────────────────────────────────┐
│ Detail Reminder                                 │
│  Subject: [_______________________]             │
│  Department: [Select▼] ── Sub Dept: [Select▼]   │
│  PIC: [Select▼] ── Kategori: [Select▼]          │
│  Prioritas: [Medium▼]                            │
│  Pesan: [Multi-line text]                       │
│                                                 │
│ Jadwal                                          │
│  Tanggal Mulai: [📅] ── Tanggal Akhir: [📅]      │
│  Jam: [🕐]                                       │
│                                                 │
│ Penerima                                        │
│  Channel: ( ) Email  ( ) WhatsApp  (•) Both      │
│  To: [Email chips]                              │
│  CC: [Email chips]                              │
│                                                 │
│ Lampiran                                        │
│  [Upload file] (PDF/Gambar/Doc, max 5MB)        │
└─────────────────────────────────────────────────┘
[  Batal  ] [  Simpan  ]
```

### 4. Calendar View
```
August 2026
Su Mo Tu We Th Fr Sa
 1  2  3  4  5  6  7
 8  9 10 11 12 13 14
15 16 17 [18] 19 20 21
22 23 [24] 25 26 27 28
29 30 31
```
- Days with reminders: highlighted background
- Color-coded by priority
- Click day → modal shows reminder list

### 5. Settings (Admin)
```
Tabs: [ SMTP & WhatsApp ] [ Master Data ] [ Cron Log ] [ Akun ]

Cron Log Tab:
  ┌─────────────────────────────────────────┐
  │ URL Cron Trigger                        │
  │ https://appreminder.zya.me/cron_...     │
  │ [ Buka URL Cron ]                       │
  └─────────────────────────────────────────┘
  
  Log Files:
  [ cron.log ] [ cron_trigger_access.log ▼ ] [ cron_output.log ]
  
  ┌─ Reminder Terjadwal Hari Ini ────────────┐
  │ ID │ Subject │ Tgl │ Jam │ Status │
  │ 60 │ Makan   │ ... │17:04│ pending │
  └──────────────────────────────────────────┘
```

## Responsive Breakpoints
| Size | Sidebar | Grid |
|---|---|---|
| ≥1024px | Fixed 260px | 4 columns |
| 768-1023px | Collapsible | 2 columns |
| ≤767px | Off-canvas | 1 column |

## Interactive Elements
- Stat cards: hover lift + accent border animation
- Table rows: hover highlight
- Buttons: hover scale (-1px) + shadow
- Calendar day: color-coded by priority level
