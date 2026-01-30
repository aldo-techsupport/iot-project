import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import RealTimeNoiseChart from '@/components/charts/real-time-noise-chart';
import NoiseStatisticsPanel from '@/components/noise-statistics-panel';
import PeriodSelector from '@/components/period-selector';
import TimeoutLogViewer from '@/components/iot/timeout-log-viewer';

interface Device {
    id: number;
    name: string;
    location: string;
}

interface NoiseCalculation {
    id: number;
    period: 'L1' | 'L2' | 'L3' | 'L4';
    min_value: number;
    max_value: number;
    average_value: number;
    leq_value: number;
    thi_average: number;
    data_count: number;
    calculation_date: string;
    updated_at: string;
}

interface Props {
    devices: Device[];
}

export default function NoiseMonitoringDashboard({ devices }: Props) {
    const [selectedDevice, setSelectedDevice] = useState<number>(devices[0]?.id || 1);
    const [selectedPeriod, setSelectedPeriod] = useState<'L1' | 'L2' | 'L3' | 'L4'>('L1');
    const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split('T')[0]);
    const [calculations, setCalculations] = useState<NoiseCalculation[]>([]);
    const [dataCount, setDataCount] = useState<Record<'L1' | 'L2' | 'L3' | 'L4', number>>({
        L1: 0,
        L2: 0,
        L3: 0,
        L4: 0,
    });
    const [loading, setLoading] = useState(false);
    const [isDeviceOffline, setIsDeviceOffline] = useState(false);

    const fetchCalculations = async () => {
        try {
            setLoading(true);
            const params = new URLSearchParams({
                device_id: selectedDevice.toString(),
                date: selectedDate,
            });

            const response = await fetch(`/api/v1/iot/noise-calculations?${params}`);
            const result = await response.json();

            if (result.success) {
                setCalculations(result.data);
            }
        } catch (error) {
            console.error('Failed to fetch calculations:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchDataCounts = async () => {
        const periods: ('L1' | 'L2' | 'L3' | 'L4')[] = ['L1', 'L2', 'L3', 'L4'];
        const counts: Record<string, number> = {};

        await Promise.all(
            periods.map(async (period) => {
                try {
                    const params = new URLSearchParams({
                        device_id: selectedDevice.toString(),
                        period,
                        date: selectedDate,
                    });

                    const response = await fetch(`/api/v1/iot/noise-data/realtime?${params}`);
                    const result = await response.json();

                    if (result.success) {
                        counts[period] = result.count;
                    }
                } catch (error) {
                    console.error(`Failed to fetch count for ${period}:`, error);
                    counts[period] = 0;
                }
            })
        );

        setDataCount(counts as Record<'L1' | 'L2' | 'L3' | 'L4', number>);
    };

    useEffect(() => {
        if (selectedDevice) {
            fetchCalculations();
            fetchDataCounts();

            // Auto-refresh data counts every 30 seconds
            const interval = setInterval(fetchDataCounts, 30000);
            return () => clearInterval(interval);
        }
    }, [selectedDevice, selectedDate]);

    const handleStatusChange = (isOffline: boolean) => {
        setIsDeviceOffline(isOffline);
    };

    const currentCalculation = calculations.find((c) => c.period === selectedPeriod);

    return (
        <AppLayout>
            <Head title="Noise Monitoring Dashboard" />

            <div className="content-header">
                <div className="container-fluid">
                    <div className="row mb-2">
                        <div className="col-sm-6">
                            <h1 className="m-0">
                                <i className="fas fa-volume-up me-2"></i>
                                Noise Monitoring Dashboard
                            </h1>
                        </div>
                        <div className="col-sm-6">
                            <ol className="breadcrumb float-sm-right">
                                <li className="breadcrumb-item">
                                    <a href="/iot">IoT</a>
                                </li>
                                <li className="breadcrumb-item active">Noise Monitoring</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div className="content">
                <div className="container-fluid">
                    {/* Offline Alert */}
                    {isDeviceOffline && (
                        <div className="row mb-3">
                            <div className="col-12">
                                <div className="alert alert-danger flex items-center gap-3 shadow-sm rounded-lg">
                                    <div className="bg-white/20 p-2 rounded-full">
                                        <i className="fas fa-exclamation-triangle text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 className="font-bold mb-0">Device Offline Detected</h5>
                                        <p className="mb-0 text-sm opacity-90">
                                            The device has stopped sending data. The system is auto-filling empty data points with zero to maintain calculation integrity.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Device & Date Selector */}
                    <div className="row mb-3">
                        <div className="col-md-6">
                            <label className="form-label">
                                <i className="fas fa-microchip me-1"></i>
                                Device
                            </label>
                            <select
                                className="form-select"
                                value={selectedDevice}
                                onChange={(e) => setSelectedDevice(Number(e.target.value))}
                            >
                                {devices.map((device) => (
                                    <option key={device.id} value={device.id}>
                                        {device.name} - {device.location}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-6">
                            <label className="form-label">
                                <i className="fas fa-calendar me-1"></i>
                                Date
                            </label>
                            <input
                                type="date"
                                className="form-control"
                                value={selectedDate}
                                onChange={(e) => setSelectedDate(e.target.value)}
                                max={new Date().toISOString().split('T')[0]}
                            />
                        </div>
                    </div>

                    {/* Period Selector */}
                    <div className="row mb-3">
                        <div className="col-12">
                            <PeriodSelector
                                selectedPeriod={selectedPeriod}
                                onChange={setSelectedPeriod}
                                dataCount={dataCount}
                            />
                        </div>
                    </div>

                    {/* Statistics Panel */}
                    <div className="row mb-3">
                        <div className="col-12">
                            <NoiseStatisticsPanel calculation={currentCalculation || null} loading={loading} />
                        </div>
                    </div>

                    {/* Timeout Logs */}
                    <div className="row mb-3">
                        <div className="col-12">
                            <TimeoutLogViewer deviceId={selectedDevice} date={selectedDate} />
                        </div>
                    </div>

                    {/* Real-Time Chart */}
                    <div className="row mb-3">
                        <div className="col-12">
                            <RealTimeNoiseChart
                                deviceId={selectedDevice}
                                period={selectedPeriod}
                                date={selectedDate}
                                autoRefresh={selectedDate === new Date().toISOString().split('T')[0]}
                                onStatusChange={handleStatusChange}
                            />
                        </div>
                    </div>

                    {/* Info Cards */}
                    <div className="row">
                        <div className="col-md-4">
                            <div className="info-box bg-light">
                                <span className="info-box-icon bg-primary">
                                    <i className="fas fa-database"></i>
                                </span>
                                <div className="info-box-content">
                                    <span className="info-box-text">Data Collection</span>
                                    <span className="info-box-number">
                                        Every 5 seconds
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div className="col-md-4">
                            <div className="info-box bg-light">
                                <span className="info-box-icon bg-success">
                                    <i className="fas fa-calculator"></i>
                                </span>
                                <div className="info-box-content">
                                    <span className="info-box-text">Calculation Method</span>
                                    <span className="info-box-number" style={{ fontSize: '1rem' }}>
                                        Sturges' Rule + Leq
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div className="col-md-4">
                            <div className="info-box bg-light">
                                <span className="info-box-icon bg-warning">
                                    <i className="fas fa-clock"></i>
                                </span>
                                <div className="info-box-content">
                                    <span className="info-box-text">Monitoring Periods</span>
                                    <span className="info-box-number">
                                        4 × 10 min/day
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
