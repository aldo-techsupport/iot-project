import React, { useState, useEffect } from 'react';
import { AlertTriangle, Info, Clock } from 'lucide-react';

interface TimeoutLog {
    id: number;
    period: string;
    expected_at: string;
    action_taken: 'copied_previous' | 'filled_zero';
    consecutive_count: number;
    details: string;
}

interface Props {
    deviceId: number;
    date: string;
}

export default function TimeoutLogViewer({ deviceId, date }: Props) {
    const [logs, setLogs] = useState<TimeoutLog[]>([]);
    const [loading, setLoading] = useState(false);

    const fetchLogs = async () => {
        try {
            setLoading(true);
            const response = await fetch(`/api/v1/iot/timeout-logs?device_id=${deviceId}&date=${date}`);
            const result = await response.json();
            if (result.success) {
                setLogs(result.data);
            }
        } catch (error) {
            console.error('Failed to fetch logs', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchLogs();
        const interval = setInterval(fetchLogs, 30000); // 30s refresh
        return () => clearInterval(interval);
    }, [deviceId, date]);

    if (logs.length === 0) return null;

    return (
        <div className="card shadow-sm border-warning">
            <div className="card-header bg-warning/10">
                <h3 className="card-title text-warning flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4" />
                    Data Collection Issues
                </h3>
            </div>
            <div className="card-body p-0">
                <div className="table-responsive" style={{ maxHeight: '200px', overflowY: 'auto' }}>
                    <table className="table table-sm table-striped mb-0">
                        <thead className="thead-light sticky top-0">
                            <tr>
                                <th>Time</th>
                                <th>Period</th>
                                <th>Issue</th>
                                <th>Action Taken</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.map((log) => (
                                <tr key={log.id}>
                                    <td className="whitespace-nowrap">
                                        {new Date(log.expected_at).toLocaleTimeString()}
                                    </td>
                                    <td>
                                        <span className="badge bg-secondary">{log.period}</span>
                                    </td>
                                    <td>
                                        <span className="text-xs text-muted">
                                            Timeout #{log.consecutive_count}
                                        </span>
                                    </td>
                                    <td>
                                        {log.action_taken === 'copied_previous' ? (
                                            <span className="badge bg-warning text-dark">
                                                <Info className="h-3 w-3 inline mr-1" />
                                                Copied Previous
                                            </span>
                                        ) : (
                                            <span className="badge bg-danger">
                                                <AlertTriangle className="h-3 w-3 inline mr-1" />
                                                Filled Zero
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
