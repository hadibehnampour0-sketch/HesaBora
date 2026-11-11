/**
 * Neptune Cinematic Scene-to-Shot Selector Engine (Decision D-147)
 * Implements intelligent screenplay-to-storyboard matching, SaaS shot scale hierarchy,
 * visual rhythm, camera choreography, and contrast cut transitions.
 */

class NeptuneSceneShotSelector {
    constructor() {
        this.shotScales = {
            EWS: { code: 'EWS', label: 'نمای بسیار باز (Extreme Wide)', defaultZoom: 1.0, intent: 'معرفی بستر بحران و وسعت بازار' },
            FS:  { code: 'FS',  label: 'نمای کامل نرم‌افزار (Full Shot)', defaultZoom: 1.05, intent: 'احاطه و تسلط مدیر بر کل اکوسیستم ERP' },
            MS:  { code: 'MS',  label: 'نمای متوسط چهره (Medium Shot)', defaultZoom: 1.15, intent: 'همدلی عاطفی با درد مخاطب و دیالوگ مشاور' },
            MCU: { code: 'MCU', label: 'نمای متوسط بسته (Medium Close-Up)', defaultZoom: 1.25, intent: 'نمایش فرآیند عملیاتی یک فرم خاص' },
            CU:  { code: 'CU',  label: 'نمای بسته ماکرو (Macro Insert)', defaultZoom: 1.45, intent: 'اثبات لحظه حقیقت با تمرکز بر ارقام و سود' },
            OTS: { code: 'OTS', label: 'دید کاربر / اول شخص (POV)', defaultZoom: 1.30, intent: 'اثبات سهولت کاربری بدون نیاز به دانش قبلی' }
        };

        this.cameraMotions = {
            PUSH_IN:   { name: 'پوش‌این متمرکز (Push-In)', cssTransform: 'scale(1.22)' },
            GLIDE_PAN: { name: 'تراولینگ روان افقی (Glide Pan)', cssTransform: 'scale(1.10) translateX(-3%)' },
            LOCKED:    { name: 'کادر قفل‌شده باوقار (Locked-Off)', cssTransform: 'scale(1.0)' },
            CRISIS_SHAKE: { name: 'لرزش بحران (Crisis Shake)', cssTransform: 'scale(1.18)' },
            DOLLY_OUT: { name: 'دالی به عقب (Dolly Out)', cssTransform: 'scale(1.02)' }
        };
    }

    /**
     * Analyze a narration text line or scene object and automatically deduce the cinematic shot parameters.
     */
    evaluateScene(sceneData) {
        const text = (sceneData.headline || '') + ' ' + (sceneData.subtitle || '') + ' ' + (sceneData.text || '');
        
        let detectedScale = 'MS';
        let detectedMotion = 'LOCKED';
        let emotionalTone = 'NEUTRAL';
        let recommendedTransition = 'CUT_ON_ACTION';
        let foleyType = 'SWOOSH';
        let zoom = 1.15;

        // 1. Detect Crisis / Friction (Scene 1 & 2)
        if (text.includes('قطع') || text.includes('کابوس') || text.includes('دود') || text.includes('سرقت') || text.includes('مغایرت') || text.includes('هدر') || text.includes('تعطیل')) {
            emotionalTone = 'CRISIS';
            detectedScale = text.includes('روزی چقدر') || text.includes('دود') ? 'EWS' : 'MCU';
            detectedMotion = 'CRISIS_SHAKE';
            recommendedTransition = 'CONTRAST_CUT';
            foleyType = 'BRAAM_ALARM';
            zoom = detectedScale === 'EWS' ? 1.08 : 1.25;
        }
        // 2. Detect Solution Reveal (Scene 3)
        else if (text.includes('راهکار') || text.includes('فرمول') || text.includes('میانگین موزون') || text.includes('محاسبه') || text.includes('صدور فاکتور')) {
            emotionalTone = 'SOLUTION_REVEAL';
            detectedScale = 'FS';
            detectedMotion = 'GLIDE_PAN';
            recommendedTransition = 'SMOOTH_SWOOSH';
            foleyType = 'SOLUTION_CHIME';
            zoom = 1.12;
        }
        // 3. Detect Verification / Macro Proof (Scene 4)
        else if (text.includes('بارکد') || text.includes('حواله') || text.includes('هشدار') || text.includes('ثانیه') || text.includes('کارت‌خوان')) {
            emotionalTone = 'PROOF';
            detectedScale = 'CU';
            detectedMotion = 'PUSH_IN';
            recommendedTransition = 'CUT_ON_ACTION';
            foleyType = 'BARCODE_BEEP';
            zoom = 1.40;
        }
        // 4. Detect Transformation & Relief (Scene 5)
        else if (text.includes('قبل از') || text.includes('بعد از') || text.includes('آرامش') || text.includes('خواب راحت') || text.includes('سود خالص')) {
            emotionalTone = 'TRANSFORMATION';
            detectedScale = 'MS';
            detectedMotion = 'DOLLY_OUT';
            recommendedTransition = 'LIGHT_BLEED_DISSOLVE';
            foleyType = 'ORCHESTRAL_SWELL';
            zoom = 1.16;
        }
        // 5. Detect Call-to-Action (Scene 6)
        else if (text.includes('دانلود') || text.includes('رایگان') || text.includes('کامنت') || text.includes('تست')) {
            emotionalTone = 'CALL_TO_ACTION';
            detectedScale = 'CU';
            detectedMotion = 'PUSH_IN';
            recommendedTransition = 'DIRECT_EYELINE_LOCK';
            foleyType = 'SUCCESS_PING';
            zoom = 1.25;
        }

        return {
            shotScale: this.shotScales[detectedScale],
            cameraMotion: this.cameraMotions[detectedMotion],
            emotionalTone,
            recommendedTransition,
            foleyType,
            targetZoom: zoom,
            blueprint: {
                shotCode: detectedScale,
                intent: this.shotScales[detectedScale].intent,
                motionName: this.cameraMotions[detectedMotion].name
            }
        };
    }

    /**
     * Generates an interactive cinematic blueprint inspector panel
     */
    renderBlueprintInspector(containerId, scenes) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let html = `
        <div class="bg-slate-950/90 border border-slate-800 rounded-2xl p-4 text-xs font-sans shadow-2xl backdrop-blur-xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-3">
                <span class="font-black text-amber-400 flex items-center gap-1.5">
                    🎬 نقشه کارگردانی و تقطیع پلان‌ها (Cinematic Shot Blueprint - D-147)
                </span>
                <span class="text-[10px] bg-slate-900 text-slate-400 px-2 py-0.5 rounded border border-slate-700 font-mono">
                    SaaS Shot Grammar v4.2
                </span>
            </div>
            <div class="space-y-2">
        `;

        scenes.forEach((sc, i) => {
            const evalResult = this.evaluateScene(sc);
            const scale = evalResult.shotScale;
            const motion = evalResult.cameraMotion;
            
            html += `
                <div class="p-2.5 rounded-xl border border-slate-800 bg-slate-900/60 flex flex-wrap items-center justify-between gap-2 hover:border-amber-500/50 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-black text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-500/40">
                            #0${i + 1}
                        </span>
                        <div>
                            <span class="font-bold text-white block">${sc.badge || 'صحنه ' + (i + 1)}</span>
                            <span class="text-[10px] text-slate-400 font-mono">${scale.label} • ${motion.name}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold ${evalResult.emotionalTone === 'CRISIS' ? 'bg-red-950 text-red-300 border border-red-800' : 'bg-emerald-950 text-emerald-300 border border-emerald-800'}">
                            ${evalResult.emotionalTone}
                        </span>
                        <span class="text-[10px] text-slate-500 font-mono">زوم: ${evalResult.targetZoom}x</span>
                    </div>
                </div>
            `;
        });

        html += `
            </div>
        </div>
        `;
        container.innerHTML = html;
    }
}

// Global instantiation
window.neptuneSceneSelector = new NeptuneSceneShotSelector();
