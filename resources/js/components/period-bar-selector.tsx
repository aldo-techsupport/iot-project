import { cn } from '@/lib/utils';
import { Period, PERIODS } from '@/types/period';

interface PeriodBarSelectorProps {
    selectedPeriod: Period;
    onChange: (period: Period) => void;
    calculations?: Record<Period, {
        leq_value: number;
        average_value: number;
        data_count: number;
    } | null>;
    onPeriodClick?: (period: Period) => void;
}

export default function PeriodBarSelector({ 
    selectedPeriod, 
    onChange, 
    calculations,
    onPeriodClick 
}: PeriodBarSelectorProps) {
    
    const getCurrentPeriod = () => {
        const hour = new Date().getHours();
        if (hour === 8) return 'L1';
        if (hour === 9) return 'L2';
        if (hour === 10) return 'L3';
        if (hour === 11) return 'L4';
        if (hour === 13) return 'L5';
        if (hour === 14) return 'L6';
        if (hour === 15) return 'L7';
        if (hour === 16) return 'L8';
        return null;
    };

    const currentPeriod = getCurrentPeriod();

    // Function to get color based on noise level (LAeq)
    const getNoiseColor = (leq: number) => {
        if (leq >= 85) return 'bg-red-500 text-white'; // Danger
        if (leq >= 80) return 'bg-orange-500 text-white'; // Warning
        if (leq >= 70) return 'bg-yellow-500 text-white'; // Caution
        return 'bg-green-500 text-white'; // Safe
    };

    return (
        <div className="space-y-2">
            {PERIODS.map((period) => {
                const calc = calculations?.[period.value];
                const isSelected = selectedPeriod === period.value;
                const isCurrent = currentPeriod === period.value;
                const hasData = calc && calc.data_count > 0;
                const leqValue = calc?.leq_value || 0;
                const avgValue = calc?.average_value || 0;

                return (
                    <button
                        key={period.value}
                        type="button"
                        onClick={() => {
                            onChange(period.value);
                            onPeriodClick?.(period.value);
                        }}
                        className={cn(
                            "w-full flex items-center gap-3 rounded-lg p-3 transition-all hover:scale-[1.02]",
                            isSelected && "ring-2 ring-primary",
                            !hasData && "opacity-50"
                        )}
                    >
                        {/* Time Label */}
                        <div className="flex-shrink-0 w-20 text-left">
                            <div className="text-lg font-bold text-foreground">
                                {period.startTime}
                            </div>
                            {isCurrent && (
                                <div className="flex items-center gap-1 text-xs text-green-500">
                                    <span className="relative flex h-2 w-2">
                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    <span>Live</span>
                                </div>
                            )}
                        </div>

                        {/* Bar with Value */}
                        <div className="flex-1 relative">
                            <div
                                className={cn(
                                    "rounded-full px-4 py-2 flex items-center justify-center transition-all duration-300",
                                    hasData ? getNoiseColor(leqValue) : "bg-muted text-muted-foreground"
                                )}
                            >
                                {hasData ? (
                                    <div className="flex items-center gap-3">
                                        <span className="text-xl font-bold">
                                            {leqValue.toFixed(2)} dB(A)
                                        </span>
                                        <span className="text-sm opacity-80">
                                            LAeq
                                        </span>
                                    </div>
                                ) : (
                                    <span className="text-sm">No data</span>
                                )}
                            </div>

                            {/* Data count indicator */}
                            {hasData && (
                                <div className="absolute -bottom-1 right-2 text-xs text-muted-foreground">
                                    {calc.data_count}/720
                                </div>
                            )}
                        </div>

                        {/* Period Label */}
                        <div className="flex-shrink-0 w-12 text-right">
                            <div className="text-sm font-semibold text-muted-foreground">
                                {period.value}
                            </div>
                        </div>
                    </button>
                );
            })}
        </div>
    );
}
