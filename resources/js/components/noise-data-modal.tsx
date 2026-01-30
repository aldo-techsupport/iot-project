import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Download, FileSpreadsheet } from 'lucide-react';
import { useState, useEffect } from 'react';

interface NoiseDataPoint {
    noise_level: number;
    temperature: number;
    humidity: number;
    measured_at: string;
    is_filled: boolean;
    fill_method: string | null;
}

interface NoiseDataModalProps {
    open: boolean;
    onClose: () => void;
    deviceId: number;
    deviceName: string;
    period: 'L1' | 'L2' | 'L3' | 'L4';
    date: string;
}

export default function NoiseDataModal({ open, onClose, deviceId, deviceName, period, date }: NoiseDataModalProps) {
    const [data, setData] = useState<NoiseDataPoint[]>([]);
    const [loading, setLoading] = useState(false);
    const [totalCollected, setTotalCollected] = useState(0);
    const [fromOfficial, setFromOfficial] = useState(0);

    useEffect(() => {
        if (open) {
            fetchData();
        }
    }, [open, deviceId, period, date]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                device_id: deviceId.toString(),
                period,
                date,
            });
            const response = await fetch(`/api/v1/iot/noise-data/realtime?${params}`);
            const result = await response.json();

            if (result.success) {
                setData(result.data);
                setTotalCollected(result.total_collected || 0);
                setFromOfficial(result.from_official_period || 0);
            }
        } catch (error) {
            console.error('Failed to fetch data:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleExport = () => {
        const params = new URLSearchParams({
            device_id: deviceId.toString(),
            period,
            date,
        });
        window.location.href = `/api/v1/iot/noise-data/export?${params}`;
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-6xl max-h-[90vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center justify-between">
                        <div>
                            <div className="text-lg font-bold">{deviceName} - {period} Data</div>
                            <div className="text-sm font-normal text-muted-foreground">{date}</div>
                        </div>
                        <button
                            onClick={handleExport}
                            className="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                        >
                            <Download className="h-4 w-4" />
                            Export Excel
                        </button>
                    </DialogTitle>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="text-muted-foreground">Loading data...</div>
                    </div>
                ) : (
                    <>
                        <div className="flex gap-4 rounded-lg bg-muted/50 p-3 text-sm">
                            <div>
                                <span className="text-muted-foreground">Data Used:</span>{' '}
                                <span className="font-semibold">{data.length}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Total Collected:</span>{' '}
                                <span className="font-semibold">{totalCollected}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">From Official Period:</span>{' '}
                                <span className="font-semibold">{fromOfficial}</span>
                            </div>
                        </div>

                        <div className="flex-1 overflow-auto">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-muted">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-semibold">No</th>
                                        <th className="px-3 py-2 text-left font-semibold">Timestamp</th>
                                        <th className="px-3 py-2 text-right font-semibold">Noise (dB)</th>
                                        <th className="px-3 py-2 text-right font-semibold">Temp (°C)</th>
                                        <th className="px-3 py-2 text-right font-semibold">Humidity (%)</th>
                                        <th className="px-3 py-2 text-center font-semibold">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map((row, index) => (
                                        <tr
                                            key={index}
                                            className={`border-b hover:bg-muted/30 ${row.is_filled ? 'bg-yellow-50 dark:bg-yellow-950/20' : ''}`}
                                        >
                                            <td className="px-3 py-2">{index + 1}</td>
                                            <td className="px-3 py-2 font-mono text-xs">
                                                {new Date(row.measured_at).toLocaleString('id-ID')}
                                            </td>
                                            <td className="px-3 py-2 text-right font-medium">{row.noise_level.toFixed(2)}</td>
                                            <td className="px-3 py-2 text-right">{row.temperature.toFixed(2)}</td>
                                            <td className="px-3 py-2 text-right">{row.humidity.toFixed(2)}</td>
                                            <td className="px-3 py-2 text-center">
                                                {row.is_filled ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                                        Failed To Fetch Data ({row.fill_method})
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">
                                                        OK
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
