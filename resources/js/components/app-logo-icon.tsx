import { SVGAttributes } from 'react';

/** Map pin with a star — the community mark. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M12 1.5c-4.56 0-8.25 3.6-8.25 8.05 0 5.86 7.13 12.2 7.43 12.47a1.24 1.24 0 0 0 1.64 0c.3-.27 7.43-6.6 7.43-12.47C20.25 5.1 16.56 1.5 12 1.5Zm.62 4.2 1.03 2.1 2.32.33-1.68 1.63.4 2.3L12.62 11l-2.07 1.08.4-2.3-1.68-1.64 2.32-.33 1.03-2.1Z"
            />
        </svg>
    );
}
