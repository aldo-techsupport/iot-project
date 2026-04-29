import { cn } from '@/lib/utils';
import { Period, PERIODS } from '@/types/period';

interface Calculation {
    leq_value: number;
    average_value: number;
    data_count: number;
    min_value: number;
    max_value: number;
}

interface PeriodBarViewProps {
    calculations: Partial<Record<Period, Calculation | null>>;
    onPeriodClick?: (period: Period) => void;
    onPeriodDoubleClick?: (period: Period) => void;
    selectedPeriod?: Period | null;
}

export default function PeriodBarView({ 
    calculations,
    onPeriodClick,
    onPeriodDoubleClick,
    selectedPeriod 
}: PeriodBarViewProps) {
    
    const getCurrentPeriod = (): Period | null => {
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
        if (leq >= 85) return {
            bg: 'bg-red-500',
            text: 'text-white',
            label: 'Danger'
        };
        if (leq >= 80) return {
            bg: 'bg-orange-500',
            text: 'text-white',
            label: 'Warning'
        };
        if (leq >= 70) return {
            bg: 'bg-yellow-500',
            text: 'text-gray-900',
            label: 'Caution'
        };
        return {
            bg: 'bg-green-500',
            text: 'text-white',
            label: 'Safe'
        };
    };

    return (
        <div className="space-y-2">
            {PERIODS.map((period) => {
                const calc = calculations[period.value];
                const isSelected = selectedPeriod === period.value;
                const isCurrent = currentPeriod === period.value;
                
                // Check if we have actual calculation data (not just raw data count)
                const hasCalculation = calc && (calc.leq_value > 0 || calc.average_value > 0);
                const hasRawData = calc && calc.data_count > 0;
                const hasData = hasCalculation || hasRawData;
                
                const leqValue = calc?.leq_value || 0;
                const avgValue = calc?.average_value || 0;
                const colorScheme = getNoiseColor(leqValue);

                return (
                    <button
                        key={period.value}
                        type="button"
                        onClick={() => onPeriodClick?.(period.value)}
                        onDoubleClick={() => onPeriodDoubleClick?.(period.value)}
                        className={cn(
                            "w-full flex items-center gap-1 sm:gap-3 rounded-lg transition-all hover:scale-[1.01]",
                            isSelected && "ring-2 ring-primary ring-offset-2",
                            !hasData && "opacity-60"
                        )}
                    >
                        {/* Time Label */}
                        <div className="flex-shrink-0 w-16 sm:w-24 text-left pl-1 sm:pl-2">
                            <div className="text-base sm:text-xl font-bold text-foreground">
                                {period.startTime}
                            </div>
                            {isCurrent && (
                                <div className="flex items-center gap-1 text-xs text-green-500 font-medium">
                                    <span className="relative flex h-2 w-2">
                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    <span className="hidden sm:inline">Live</span>
                                </div>
                            )}
                        </div>

                        {/* Bar with Value */}
                        <div className="flex-1 relative">
                            {hasCalculation ? (
                                <div
                                    className={cn(
                                        "rounded-lg px-6 py-3 flex items-center justify-between transition-all duration-300 shadow-sm",
                                        `${colorScheme.bg} ${colorScheme.text}`
                                    )}
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="flex flex-col">
                                            <span className="text-2xl font-bold">
                                                {leqValue.toFixed(1)} dB
                                            </span>
                                            <span className="text-xs opacity-80">
                                                LAeq
                                            </span>
                                        </div>
                                        <div className="h-8 w-px bg-current opacity-30"></div>
                                        <div className="flex flex-col text-sm">
                                            <span className="opacity-80">
                                                Avg: {avgValue.toFixed(1)} dB
                                            </span>
                                            <span className="text-xs opacity-60">
                                                {calc.data_count}/720 points
                                            </span>
                                        </div>
                                    </div>
                                    
                                    {/* Status Badge */}
                                    <div className="flex items-center gap-2">
                                        <span className={cn(
                                            "px-2 py-1 rounded text-xs font-semibold",
                                            "bg-white/20 backdrop-blur-sm"
                                        )}>
                                            {colorScheme.label}
                                        </span>
                                    </div>
                                </div>
                            ) : hasRawData ? (
                                <div className="relative rounded-lg overflow-hidden bg-muted/50">
                                    {/* Progress Bar Background */}
                                    <div 
                                        className="bg-green-500 text-white px-3 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-2 transition-all duration-500 shadow-sm"
                                        style={{ width: `${(calc.data_count / 720) * 100}%`, minWidth: '150px' }}
                                    >
                                        <div className="flex flex-col sm:flex-row sm:items-center gap-0.5 sm:gap-2">
                                            <span className="text-xs sm:text-sm font-medium whitespace-nowrap">Collecting data...</span>
                                            <span className="text-xs opacity-80 whitespace-nowrap">
                                                {calc.data_count}/720 points
                                            </span>
                                        </div>
                                        <span className="text-xs font-bold px-2 py-0.5 rounded bg-white/20 backdrop-blur-sm whitespace-nowrap self-start sm:self-auto">
                                            {((calc.data_count / 720) * 100).toFixed(1)}%
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-lg px-6 py-3 bg-muted text-muted-foreground border border-dashed">
                                    <span className="text-sm">No data collected</span>
                                </div>
                            )}
                        </div>

                        {/* Period Label */}
                        <div className="flex-shrink-0 w-12 sm:w-16 text-right pr-1 sm:pr-2">
                            <div className="text-base sm:text-lg font-bold text-muted-foreground">
                                {period.value}
                            </div>
                            <div className="text-xs text-muted-foreground hidden sm:block">
                                {period.endTime}
                            </div>
                        </div>
                    </button>
                );
            })}
        </div>
    );
}
