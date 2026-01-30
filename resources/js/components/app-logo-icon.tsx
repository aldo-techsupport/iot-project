import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            {/* Circuit board pattern */}
            <g stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" strokeLinejoin="round">
                {/* Outer frame */}
                <rect x="2" y="2" width="20" height="20" rx="2" />

                {/* Circuit nodes - corners */}
                <circle cx="5" cy="5" r="1.2" fill="currentColor" />
                <circle cx="19" cy="5" r="1.2" fill="currentColor" />
                <circle cx="5" cy="19" r="1.2" fill="currentColor" />
                <circle cx="19" cy="19" r="1.2" fill="currentColor" />

                {/* Circuit nodes - sides */}
                <circle cx="12" cy="5" r="1.2" fill="currentColor" />
                <circle cx="12" cy="19" r="1.2" fill="currentColor" />
                <circle cx="5" cy="12" r="1.2" fill="currentColor" />
                <circle cx="19" cy="12" r="1.2" fill="currentColor" />

                {/* Circuit connections */}
                <line x1="5" y1="5" x2="12" y2="5" />
                <line x1="12" y1="5" x2="19" y2="5" />
                <line x1="5" y1="19" x2="12" y2="19" />
                <line x1="12" y1="19" x2="19" y2="19" />
                <line x1="5" y1="5" x2="5" y2="12" />
                <line x1="5" y1="12" x2="5" y2="19" />
                <line x1="19" y1="5" x2="19" y2="12" />
                <line x1="19" y1="12" x2="19" y2="19" />

                {/* Monitoring wave in center */}
                <path d="M 7 12 L 9 12 L 10 9 L 11 15 L 12 12 L 14 12 L 15 10 L 17 12" strokeWidth="2" />
            </g>
        </svg>
    );
}
