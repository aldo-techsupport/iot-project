import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import PeriodBarView from '@/components/period-bar-view';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useState } from 'react';
import { Period } from '@/types/period';

interface Calculation {
    leq_value: number;
    average_value: number;
    data_count: number;
    min_value: number;
    max_value: number;
}

export default function NoiseBarViewDemo() {
    const [selectedPeriod, setSelectedPeriod] = useState<Period | null>(null);

    // Demo data - replace with real data from backend
    const demoCalculations: Partial<Record<Period, Calculation | null>> = {
        'L1': {
            leq_value: 28.1,
            average_value: 27.5,
            data_count: 720,
            min_value: 25.0,
            max_value: 32.0,
        },
        'L2': {
            leq_value: 30.2,
            average_value: 29.8,
            data_count: 720,
            min_value: 27.0,
            max_value: 35.0,
        },
        'L3': {
            leq_value: 26.9,
            average_value: 26.5,
            data_count: 720,
            min_value: 24.0,
            max_value: 30.0,
        },
        'L4': {
            leq_value: 23.0,
            average_value: 22.8,
            data_count: 450,
            min_value: 20.0,
            max_value: 26.0,
        },
        'L5': null, // No data yet
        'L6': null,
        'L7': null,
        'L8': null,
    };

    return (
        <AppLayout>
            <Head title="Noise Monitoring - Bar View" />

            <div className="container mx-auto p-6 space-y-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Noise Monitoring</h1>
                    <p className="text-muted-foreground">
                        8-hour work period monitoring with bar visualization
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Bar View */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Daily Noise Levels</CardTitle>
                                <CardDescription>
                                    LAeq values for each 1-hour period (08:00 - 17:00, skip 12:00-13:00)
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <PeriodBarView
                                    calculations={demoCalculations}
                                    selectedPeriod={selectedPeriod}
                                    onPeriodClick={(period) => setSelectedPeriod(period)}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Details Panel */}
                    <div className="lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>Period Details</CardTitle>
                                <CardDescription>
                                    {selectedPeriod ? `Selected: ${selectedPeriod}` : 'Click a period to view details'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {selectedPeriod && demoCalculations[selectedPeriod] ? (
                                    <div className="space-y-4">
                                        <div>
                                            <div className="text-sm text-muted-foreground">LAeq</div>
                                            <div className="text-2xl font-bold">
                                                {demoCalculations[selectedPeriod]!.leq_value.toFixed(1)} dB
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground">Average</div>
                                            <div className="text-xl font-semibold">
                                                {demoCalculations[selectedPeriod]!.average_value.toFixed(1)} dB
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <div className="text-sm text-muted-foreground">Min</div>
                                                <div className="text-lg font-semibold">
                                                    {demoCalculations[selectedPeriod]!.min_value.toFixed(1)} dB
                                                </div>
                                            </div>
                                            <div>
                                                <div className="text-sm text-muted-foreground">Max</div>
                                                <div className="text-lg font-semibold">
                                                    {demoCalculations[selectedPeriod]!.max_value.toFixed(1)} dB
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground">Data Points</div>
                                            <div className="text-lg font-semibold">
                                                {demoCalculations[selectedPeriod]!.data_count} / 720
                                            </div>
                                            <div className="mt-2 h-2 bg-muted rounded-full overflow-hidden">
                                                <div 
                                                    className="h-full bg-primary transition-all"
                                                    style={{ 
                                                        width: `${(demoCalculations[selectedPeriod]!.data_count / 720) * 100}%` 
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="text-center text-muted-foreground py-8">
                                        {selectedPeriod ? 'No data available for this period' : 'Select a period to view details'}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Legend */}
                        <Card className="mt-4">
                            <CardHeader>
                                <CardTitle className="text-sm">Safety Levels</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <div className="w-4 h-4 rounded bg-green-500"></div>
                                    <span className="text-sm">&lt; 70 dB - Safe</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-4 h-4 rounded bg-yellow-500"></div>
                                    <span className="text-sm">70-79 dB - Caution</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-4 h-4 rounded bg-orange-500"></div>
                                    <span className="text-sm">80-84 dB - Warning</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-4 h-4 rounded bg-red-500"></div>
                                    <span className="text-sm">≥ 85 dB - Danger</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
