@props(['class' => 'w-10 h-10', 'showText' => false, 'subtext' => null])

<div class="flex items-center gap-3">
    <div class="{{ $class }} relative flex items-center justify-center shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%" fill="none" class="w-full h-full drop-shadow-[0_0_12px_rgba(0,240,255,0.35)]">
            <defs>
                <radialGradient id="nepPlanetGrad" cx="35%" cy="30%" r="70%">
                    <stop offset="0%" stop-color="#38BDF8" />
                    <stop offset="35%" stop-color="#0284C7" />
                    <stop offset="70%" stop-color="#1E40AF" />
                    <stop offset="100%" stop-color="#0B132B" />
                </radialGradient>
                <radialGradient id="nepAtmoGlow" cx="40%" cy="35%" r="65%">
                    <stop offset="0%" stop-color="#00F0FF" stop-opacity="0.6" />
                    <stop offset="60%" stop-color="#0284C7" stop-opacity="0.15" />
                    <stop offset="100%" stop-color="#0284C7" stop-opacity="0" />
                </radialGradient>
                <linearGradient id="nepRingGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#00F0FF" stop-opacity="0.95" />
                    <stop offset="50%" stop-color="#38BDF8" stop-opacity="0.8" />
                    <stop offset="100%" stop-color="#2563EB" stop-opacity="0.3" />
                </linearGradient>
                <linearGradient id="nepRingGrad2" x1="100%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#67E8F9" stop-opacity="0.9" />
                    <stop offset="50%" stop-color="#0EA5E9" stop-opacity="0.7" />
                    <stop offset="100%" stop-color="#1D4ED8" stop-opacity="0.25" />
                </linearGradient>
                <filter id="nepGlow" x="-30%" y="-30%" width="160%" height="160%">
                    <feGaussianBlur stdDeviation="6" result="blur" />
                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
            </defs>
            <circle cx="100" cy="100" r="70" fill="url(#nepAtmoGlow)" filter="url(#nepGlow)" />
            <path d="M 40,135 C 20,105 45,65 100,55 C 150,45 178,75 168,102" stroke="url(#nepRingGrad1)" stroke-width="4.5" stroke-linecap="round" stroke-dasharray="140 10" opacity="0.5" />
            <path d="M 160,135 C 180,105 155,65 100,55 C 50,45 22,75 32,102" stroke="url(#nepRingGrad2)" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="130 15" opacity="0.4" />
            <circle cx="100" cy="100" r="46" fill="url(#nepPlanetGrad)" stroke="#38BDF8" stroke-width="1.5" stroke-opacity="0.4" />
            <ellipse cx="94" cy="94" rx="42" ry="42" fill="none" stroke="#00F0FF" stroke-width="2.5" opacity="0.35" />
            <path d="M 32,102 C 22,125 50,155 100,155 C 155,155 180,125 168,102" stroke="url(#nepRingGrad1)" stroke-width="5" stroke-linecap="round" filter="url(#nepGlow)" />
            <path d="M 32,102 C 22,125 50,155 100,155 C 155,155 180,125 168,102" stroke="#E0F2FE" stroke-width="1.8" stroke-linecap="round" opacity="0.8" />
            <path d="M 168,98 C 178,122 150,152 100,152 C 45,152 22,122 34,98" stroke="url(#nepRingGrad2)" stroke-width="4" stroke-linecap="round" />
            <circle cx="152" cy="138" r="4.5" fill="#00F0FF" filter="url(#nepGlow)" />
            <circle cx="152" cy="138" r="2" fill="#FFFFFF" />
            <circle cx="48" cy="68" r="3.5" fill="#38BDF8" filter="url(#nepGlow)" />
            <circle cx="48" cy="68" r="1.5" fill="#FFFFFF" />
        </svg>
    </div>

    @if($showText)
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xl font-black tracking-tight text-base-content">نپتون</span>
                <span class="badge badge-info badge-xs py-0.5 px-2 font-mono text-[10px] font-bold text-white bg-sky-500 border-none">ERP v2.5</span>
            </div>
            <span class="text-[11px] text-base-content/60 block font-medium">
                {{ $subtext ?? 'اقیانوس آرام حسابداری و مدیریت مالی کسب‌وکار' }}
            </span>
        </div>
    @endif
</div>
