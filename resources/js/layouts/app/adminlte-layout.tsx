import { AppContent } from '@/components/app-content';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { type PropsWithChildren, useEffect } from 'react';

interface AdminLTELayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
}

export default function AdminLTELayout({
    children,
    breadcrumbs = [],
    title = 'Dashboard',
}: AdminLTELayoutProps) {
    useEffect(() => {
        // Initialize AdminLTE after component mounts
        const initAdminLTE = async () => {
            // Import AdminLTE JS dynamically
            await import('admin-lte');
        };

        initAdminLTE();
    }, []);

    return (
        <>
            <Head title={title} />
            <div className="wrapper">
                {/* Navbar */}
                <nav className="main-header navbar navbar-expand navbar-white navbar-light">
                    {/* Left navbar links */}
                    <ul className="navbar-nav">
                        <li className="nav-item">
                            <a
                                className="nav-link"
                                data-widget="pushmenu"
                                href="#"
                                role="button"
                            >
                                <i className="fas fa-bars"></i>
                            </a>
                        </li>
                        <li className="nav-item d-none d-sm-inline-block">
                            <a href="/" className="nav-link">
                                Home
                            </a>
                        </li>
                        <li className="nav-item d-none d-sm-inline-block">
                            <a href="/dashboard" className="nav-link">
                                Dashboard
                            </a>
                        </li>
                    </ul>

                    {/* Right navbar links */}
                    <ul className="navbar-nav ml-auto">
                        {/* Fullscreen Toggle */}
                        <li className="nav-item">
                            <a
                                className="nav-link"
                                data-widget="fullscreen"
                                href="#"
                                role="button"
                            >
                                <i className="fas fa-expand-arrows-alt"></i>
                            </a>
                        </li>
                    </ul>
                </nav>

                {/* Main Sidebar Container */}
                <aside className="main-sidebar sidebar-dark-primary elevation-4">
                    {/* Brand Logo */}
                    <a href="/" className="brand-link">
                        <img
                            src="/images/logo.png"
                            alt="Logo"
                            className="brand-image img-circle elevation-3"
                            style={{ opacity: 0.8 }}
                        />
                        <span className="brand-text font-weight-light">
                            IoT App
                        </span>
                    </a>

                    {/* Sidebar */}
                    <div className="sidebar">
                        {/* Sidebar Menu */}
                        <nav className="mt-2">
                            <ul
                                className="nav nav-pills nav-sidebar flex-column"
                                data-widget="treeview"
                                role="menu"
                                data-accordion="false"
                            >
                                {/* Dashboard */}
                                <li className="nav-item">
                                    <a
                                        href="/dashboard"
                                        className="nav-link active"
                                    >
                                        <i className="nav-icon fas fa-tachometer-alt"></i>
                                        <p>Dashboard</p>
                                    </a>
                                </li>

                                {/* Devices */}
                                <li className="nav-item">
                                    <a href="#" className="nav-link">
                                        <i className="nav-icon fas fa-microchip"></i>
                                        <p>
                                            Devices
                                            <i className="right fas fa-angle-left"></i>
                                        </p>
                                    </a>
                                    <ul className="nav nav-treeview">
                                        <li className="nav-item">
                                            <a
                                                href="/devices"
                                                className="nav-link"
                                            >
                                                <i className="far fa-circle nav-icon"></i>
                                                <p>All Devices</p>
                                            </a>
                                        </li>
                                        <li className="nav-item">
                                            <a
                                                href="/devices/create"
                                                className="nav-link"
                                            >
                                                <i className="far fa-circle nav-icon"></i>
                                                <p>Add Device</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {/* Sensors */}
                                <li className="nav-item">
                                    <a href="#" className="nav-link">
                                        <i className="nav-icon fas fa-thermometer-half"></i>
                                        <p>
                                            Sensors
                                            <i className="right fas fa-angle-left"></i>
                                        </p>
                                    </a>
                                    <ul className="nav nav-treeview">
                                        <li className="nav-item">
                                            <a
                                                href="/sensors"
                                                className="nav-link"
                                            >
                                                <i className="far fa-circle nav-icon"></i>
                                                <p>All Sensors</p>
                                            </a>
                                        </li>
                                        <li className="nav-item">
                                            <a
                                                href="/sensors/create"
                                                className="nav-link"
                                            >
                                                <i className="far fa-circle nav-icon"></i>
                                                <p>Add Sensor</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {/* Analytics */}
                                <li className="nav-item">
                                    <a href="/analytics" className="nav-link">
                                        <i className="nav-icon fas fa-chart-line"></i>
                                        <p>Analytics</p>
                                    </a>
                                </li>

                                {/* Settings */}
                                <li className="nav-item">
                                    <a href="/settings" className="nav-link">
                                        <i className="nav-icon fas fa-cog"></i>
                                        <p>Settings</p>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </aside>

                {/* Content Wrapper */}
                <div className="content-wrapper">
                    {/* Content Header (Page header) */}
                    {breadcrumbs.length > 0 && (
                        <div className="content-header">
                            <div className="container-fluid">
                                <div className="row mb-2">
                                    <div className="col-sm-6">
                                        <h1 className="m-0">{title}</h1>
                                    </div>
                                    <div className="col-sm-6">
                                        <ol className="breadcrumb float-sm-right">
                                            {breadcrumbs.map((item, index) => (
                                                <li
                                                    key={index}
                                                    className={`breadcrumb-item ${index === breadcrumbs.length - 1 ? 'active' : ''}`}
                                                >
                                                    {item.href ? (
                                                        <a href={item.href}>
                                                            {item.title}
                                                        </a>
                                                    ) : (
                                                        item.title
                                                    )}
                                                </li>
                                            ))}
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Main content */}
                    <div className="content">
                        <div className="container-fluid">{children}</div>
                    </div>
                </div>

                {/* Footer */}
                <footer className="main-footer">
                    <strong>
                        Copyright &copy; 2024-2026{' '}
                        <a href="https://adminlte.io">IoT Application</a>.
                    </strong>
                    All rights reserved.
                    <div className="float-right d-none d-sm-inline-block">
                        <b>Version</b> 1.0.0
                    </div>
                </footer>
            </div>
        </>
    );
}
