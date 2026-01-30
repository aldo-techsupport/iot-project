import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { home } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { name } = usePage<SharedData>().props;

    return (
        <div className="relative min-h-screen w-full">
            <div
                className="absolute inset-0 bg-cover bg-center bg-no-repeat"
                style={{
                    backgroundImage: "url('/images/bg-full.png')"
                }}
            />
            <div className="absolute inset-0 bg-black/40" />

            <div className="relative z-10 flex min-h-screen w-full items-center justify-center p-4 lg:justify-end lg:pr-32">
                <Card className="w-full max-w-[400px] border-blue-500/30 bg-slate-950/60 text-white shadow-2xl backdrop-blur-md sm:rounded-xl">
                    <CardHeader className="text-center">
                        <Link
                            href={home()}
                            className="mx-auto mb-6 flex items-center justify-center"
                        >
                            <AppLogoIcon className="h-12 text-blue-500" />
                        </Link>
                        <CardTitle className="text-2xl font-bold tracking-tight text-white">{title}</CardTitle>
                        <CardDescription className="text-zinc-400">
                            {description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {children}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
