# UI Components

## Date & Time Pickers

### DateTimePicker24h
Komponen untuk memilih tanggal dan waktu dengan format 24 jam (HH:mm).

**Usage:**
```tsx
import { DateTimePicker24h } from '@/components/ui/date-time-picker-24h';

const [date, setDate] = useState<Date | undefined>();

<DateTimePicker24h
  date={date}
  setDate={setDate}
  placeholder="Select date & time"
/>
```

**Features:**
- Format 24 jam (00:00 - 23:59)
- Interval menit: 5 menit (00, 05, 10, ..., 55)
- Calendar picker untuk tanggal
- Scroll area untuk jam dan menit
- Format tampilan: dd/MM/yyyy HH:mm

### DatePicker
Komponen untuk memilih tanggal saja (tanpa waktu).

**Usage:**
```tsx
import { DatePicker } from '@/components/ui/date-picker';

const [date, setDate] = useState<Date | undefined>();

<DatePicker
  date={date}
  setDate={setDate}
  placeholder="Pick a date"
  maxDate={new Date()} // Optional: maksimal tanggal hari ini
  disabled={false} // Optional
/>
```

**Features:**
- Calendar picker
- Format tampilan: dd/MM/yyyy
- Support max date constraint
- Support disabled state

## Dependencies
- `react-day-picker`: Calendar component
- `date-fns`: Date formatting
- `@radix-ui/react-popover`: Popover component
- `@radix-ui/react-scroll-area`: Scroll area component
