import { cn } from '@/lib/utils';
import { Check, Clock } from 'lucide-react';
import { Period, PERIODS } from '@/types/period';

interface PeriodSelectorProps {
    selectedPeriod: Period;
    onChange: (period: Period) => void;
    dataCount?: Record<Period, number>;
    onPeriodDoubleClick?: (period: Period) => void;
}

export default function PeriodSelector({ selectedPeriod, onChange, dataCount, onPeriodDoubleClick }: PeriodSelectorProps) {
    const periods = PERIODS;

    const getCurrentPeriod = () => {
        const hour = new Date().getHours();
        if (hour >= 8 && hour < 10) return 'L1';
        if (hour >= 10 && hour < 13) return 'L2';
        if (hour >= 13 && hour < 15) return 'L3';
        if (hour >= 15 && hour < 17) return 'L4';
        return null;
    };

    const currentPeriod = getCurrentPeriod();

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {periods.map((period) => {
                const count = dataCount?.[period.value] || 0;
                const isComplete = count >= 720;
                const isSelected = selectedPeriod === period.value;
                const isCurrent = currentPeriod === period.value;

                return (
                    <button
                        key={period.value}
                        type="button"
                        onClick={() => onChange(period.value)}
                        onDoubleClick={() => onPeriodDoubleClick?.(period.value)}
                        className={cn(
                            "relative flex flex-col items-center justify-center rounded-xl border p-3 transition-all hover:bg-muted/50 cursor-pointer",
                            isSelected && "border-primary bg-primary/5 ring-1 ring-primary",
                            !isSelected && "bg-card shadow-sm"
                        )}
                        title="Double-click to view data details"
                    >
                        {isCurrent && (
                            <span className="absolute right-2 top-2 flex h-2 w-2">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                        )}

                        <div className="mb-1 text-sm font-semibold">{period.label}</div>
                        <div className="mb-2 text-xs text-muted-foreground">{period.targetRange}</div>

                        <div className="flex w-full items-center justify-between gap-2 rounded-lg bg-muted/50 px-2 py-1">
                            <div className="flex items-center gap-1.5 overflow-hidden">
                                <Clock className="h-3 w-3 text-muted-foreground flex-shrink-0" />
                                <div
                                    className={cn(
                                        "h-1.5 flex-1 rounded-full bg-muted overflow-hidden w-16",
                                    )}
                                >
                                    <div
                                        className={cn("h-full transition-all duration-500",
                                            isComplete ? "bg-green-500" : "bg-primary"
                                        )}
                                        style={{ width: `${Math.min((count / 720) * 100, 100)}%` }}
                                    />
                                </div>
                            </div>
                            <span className={cn(
                                "text-xs font-medium",
                                isComplete ? "text-green-600 dark:text-green-400" : "text-muted-foreground"
                            )}>
                                {count}/720
                            </span>
                        </div>

                        {isComplete && (
                            <div className="absolute -right-1 -top-1 rounded-full bg-green-500 p-0.5 text-white shadow-sm">
                                <Check className="h-3 w-3" />
                            </div>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
