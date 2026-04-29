// Period type definition for noise monitoring system
export type Period = 'L1' | 'L2' | 'L3' | 'L4' | 'L5' | 'L6' | 'L7' | 'L8';

// Period configuration with time ranges
export const PERIODS: Array<{
    value: Period;
    label: string;
    subLabel: string;
    targetRange: string;
    startTime: string;
    endTime: string;
}> = [
    { value: 'L1', label: 'L1 (08:00)', subLabel: '08:00 - 09:00', targetRange: '08:00 - 09:00', startTime: '08:00', endTime: '09:00' },
    { value: 'L2', label: 'L2 (09:00)', subLabel: '09:00 - 10:00', targetRange: '09:00 - 10:00', startTime: '09:00', endTime: '10:00' },
    { value: 'L3', label: 'L3 (10:00)', subLabel: '10:00 - 11:00', targetRange: '10:00 - 11:00', startTime: '10:00', endTime: '11:00' },
    { value: 'L4', label: 'L4 (11:00)', subLabel: '11:00 - 12:00', targetRange: '11:00 - 12:00', startTime: '11:00', endTime: '12:00' },
    // LUNCH BREAK: 12:00 - 13:00 (SKIP)
    { value: 'L5', label: 'L5 (13:00)', subLabel: '13:00 - 14:00', targetRange: '13:00 - 14:00', startTime: '13:00', endTime: '14:00' },
    { value: 'L6', label: 'L6 (14:00)', subLabel: '14:00 - 15:00', targetRange: '14:00 - 15:00', startTime: '14:00', endTime: '15:00' },
    { value: 'L7', label: 'L7 (15:00)', subLabel: '15:00 - 16:00', targetRange: '15:00 - 16:00', startTime: '15:00', endTime: '16:00' },
    { value: 'L8', label: 'L8 (16:00)', subLabel: '16:00 - 17:00', targetRange: '16:00 - 17:00', startTime: '16:00', endTime: '17:00' },
];

// Data points per period (1 hour @ 5 second interval)
export const DATA_POINTS_PER_PERIOD = 720;
