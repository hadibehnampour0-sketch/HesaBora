/**
 * Neptune ERP Cinematic Commercial Suite
 * =========================================================================
 * KINETIC MOVIE ENGINE (DECISION D-151 / GUARDRAIL 73)
 * =========================================================================
 * 1. Virtual Animated Cursor & Kinetic UI Simulator (Clicks, Ripples, Typing, Ticker)
 * 2. Hormozi / Apple-Style Kinetic Word-by-Word Subtitles & Emoji Popups
 * 3. Micro-Shot Pacing & Cinematic Dynamic Camera Pan/Zoom (Ken Burns & Crash Zooms)
 * 4. AI Moving Video Atmosphere (Volumetric Light, Dust Motes, Scanning Lasers, Breathing)
 *
 * 100% Offline-First | Zero Cloud Dependencies | Pure WebGL / Web Audio / Vanilla JS
 */

class NeptuneKineticMovieEngine {
    constructor(options = {}) {
        this.stageContainer = document.getElementById(options.stageId || 'videoStage');
        this.stageImage = document.getElementById(options.imageId || 'stageImage');
        this.audioElement = options.audioElement || document.getElementById('episodeAudio');
        this.soundEngine = options.soundEngine || window.cinematicSound;
        
        this.enabled = true;
        this.activeSceneIndex = -1;
        this.sceneTimeline = null;
        this.animFrameId = null;

        // Sub-Engines
        this.cursor = null;
        this.typography = null;
        this.cameraPacer = null;
        this.atmosphere = null;

        this.init();
    }

    init() {
        if (!this.stageContainer) return;

        // 1. Initialize Virtual Cursor Simulator
        this.cursor = new VirtualCursorSimulator(this.stageContainer, this.soundEngine);

        // 2. Initialize Kinetic Typography Engine
        this.typography = new KineticTypographyEngine(this.stageContainer);

        // 3. Initialize Micro-Shot Camera Pacer
        this.cameraPacer = new MicroShotPacerEngine(this.stageImage);

        // 4. Initialize Atmospheric Video Motion Engine
        this.atmosphere = new AtmosphericVideoEngine(this.stageContainer);

        // Attach audio sync listener
        if (this.audioElement) {
            this.audioElement.addEventListener('timeupdate', () => this.onTimeUpdate());
            this.audioElement.addEventListener('seeked', () => this.onSeeked());
        }

        // Add Sound FX Extensions to soundEngine if not present
        this.injectSoundEffects();

        // Start render loop
        this.startLoop();
        console.log("🎬 [D-151] Neptune Kinetic Movie Engine initialized successfully.");
    }

    injectSoundEffects() {
        if (!this.soundEngine) return;

        // Mechanical Mouse Click SFX
        if (!this.soundEngine.playMouseClick) {
            this.soundEngine.playMouseClick = () => {
                if (!this.soundEngine.enabled) return;
                this.soundEngine.init();
                const now = this.soundEngine.ctx.currentTime;

                const osc = this.soundEngine.ctx.createOscillator();
                const gain = this.soundEngine.ctx.createGain();
                const filter = this.soundEngine.ctx.createBiquadFilter();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(1400, now);
                osc.frequency.exponentialRampToValueAtTime(120, now + 0.04);

                filter.type = 'highpass';
                filter.frequency.value = 800;

                gain.gain.setValueAtTime(0.45, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.045);

                osc.connect(filter);
                filter.connect(gain);
                gain.connect(this.soundEngine.sfxGain || this.soundEngine.ctx.destination);

                osc.start(now);
                osc.stop(now + 0.05);
            };
        }

        // Tactile Mechanical Keyboard Typing SFX
        if (!this.soundEngine.playKeyboardType) {
            this.soundEngine.playKeyboardType = () => {
                if (!this.soundEngine.enabled) return;
                this.soundEngine.init();
                const now = this.soundEngine.ctx.currentTime;

                const osc = this.soundEngine.ctx.createOscillator();
                const gain = this.soundEngine.ctx.createGain();

                osc.type = 'triangle';
                osc.frequency.setValueAtTime(450 + Math.random() * 200, now);
                osc.frequency.exponentialRampToValueAtTime(80, now + 0.035);

                gain.gain.setValueAtTime(0.3, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.04);

                osc.connect(gain);
                gain.connect(this.soundEngine.sfxGain || this.soundEngine.ctx.destination);

                osc.start(now);
                osc.stop(now + 0.045);
            };
        }

        // Rapid Numeric Ticker Sound
        if (!this.soundEngine.playNumberTick) {
            this.soundEngine.playNumberTick = () => {
                if (!this.soundEngine.enabled) return;
                this.soundEngine.init();
                const now = this.soundEngine.ctx.currentTime;

                const osc = this.soundEngine.ctx.createOscillator();
                const gain = this.soundEngine.ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(880 + Math.random() * 120, now);
                gain.gain.setValueAtTime(0.18, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.02);

                osc.connect(gain);
                gain.connect(this.soundEngine.sfxGain || this.soundEngine.ctx.destination);

                osc.start(now);
                osc.stop(now + 0.025);
            };
        }
    }

    /**
     * Load kinetic choreography for a specific scene
     */
    loadSceneChoreography(sceneIndex, sceneData) {
        this.activeSceneIndex = sceneIndex;
        this.sceneTimeline = sceneData.kineticChoreography || this.generateDefaultChoreography(sceneIndex, sceneData);

        // Reset sub-engines for new scene
        this.cursor.reset();
        this.typography.setSceneText(sceneData.subtitle || sceneData.headline || '', this.sceneTimeline.wordTimings);
        this.cameraPacer.setMicroShots(this.sceneTimeline.microShots || []);
        this.atmosphere.setSceneMode(sceneIndex, sceneData);

        console.log(`🎬 [D-151] Loaded kinetic choreography for Scene ${sceneIndex + 1}: ${this.sceneTimeline.microShots ? this.sceneTimeline.microShots.length : 0} micro-shots, ${this.sceneTimeline.uiActions ? this.sceneTimeline.uiActions.length : 0} UI actions.`);
    }

    onTimeUpdate() {
        if (!this.audioElement || !this.sceneTimeline) return;
        const currentTime = this.audioElement.currentTime;

        // 1. Update Subtitles & Word-by-Word highlighting
        this.typography.update(currentTime, this.audioElement.duration || 25);

        // 2. Update UI Actions (Cursor movements, clicks, typing, tickers)
        if (this.sceneTimeline.uiActions) {
            this.sceneTimeline.uiActions.forEach(action => {
                if (!action.triggered && currentTime >= action.time) {
                    action.triggered = true;
                    this.executeUIAction(action);
                }
            });
        }

        // 3. Update Micro-Shot Camera Position
        this.cameraPacer.update(currentTime);
    }

    onSeeked() {
        if (!this.audioElement || !this.sceneTimeline) return;
        const currentTime = this.audioElement.currentTime;

        // Re-evaluate triggered actions
        if (this.sceneTimeline.uiActions) {
            this.sceneTimeline.uiActions.forEach(action => {
                action.triggered = (currentTime >= action.time);
            });
        }
    }

    executeUIAction(action) {
        if (action.type === 'MOVE') {
            this.cursor.moveTo(action.x, action.y, action.duration || 800);
        } else if (action.type === 'CLICK') {
            this.cursor.clickAt(action.x, action.y, action.label, () => {
                if (action.onComplete) action.onComplete();
            });
        } else if (action.type === 'TYPE') {
            this.cursor.typeText(action.text, action.x, action.y, action.duration || 1200);
        } else if (action.type === 'TICKER') {
            this.cursor.runTicker(action.from, action.to, action.duration || 1800, action.x, action.y, action.prefix, action.suffix, action.isAlert);
        } else if (action.type === 'BANNER') {
            this.cursor.showToastBanner(action.title, action.desc, action.tone || 'success', action.duration || 3000);
        }
    }

    startLoop() {
        const loop = () => {
            if (this.enabled) {
                this.atmosphere.render();
                this.cameraPacer.renderTick();
            }
            this.animFrameId = requestAnimationFrame(loop);
        };
        this.animFrameId = requestAnimationFrame(loop);
    }

    /**
     * Fallback automatic choreography generation if not explicitly coded in scene
     */
    generateDefaultChoreography(sceneIndex, sceneData) {
        const duration = 25; // standard scene duration
        return {
            microShots: [
                { start: 0, end: 4, shot: 'WIDE_ESTABLISH', zoom: 1.05, originX: 50, originY: 50, label: 'EWS // ESTABLISHING' },
                { start: 4, end: 9, shot: 'CRASH_ZOOM_IN', zoom: 1.32, originX: sceneData.arrow.x, originY: sceneData.arrow.y, label: 'CU // CRASH FOCUS' },
                { start: 9, end: 16, shot: 'SLOW_PAN', zoom: 1.25, originX: sceneData.arrow.x + 10, originY: sceneData.arrow.y - 10, label: 'MCU // PANNING SCAN' },
                { start: 16, end: 21, shot: 'PUNCH_IN', zoom: 1.38, originX: sceneData.arrow.x - 5, originY: sceneData.arrow.y + 5, label: 'ECU // PUNCH-IN DETAIL' },
                { start: 21, end: duration, shot: 'PULL_BACK', zoom: 1.10, originX: 50, originY: 50, label: 'MLS // WIDE RE-ESTABLISH' }
            ],
            uiActions: [
                { time: 1.5, type: 'MOVE', x: 25, y: 35, duration: 900 },
                { time: 4.0, type: 'CLICK', x: sceneData.arrow.x, y: sceneData.arrow.y, label: sceneData.pointerText },
                { time: 12.0, type: 'MOVE', x: 60, y: 70, duration: 1100 }
            ]
        };
    }
}

// =========================================================================
// SUB-ENGINE 1: VIRTUAL CURSOR & KINETIC UI SIMULATOR
// =========================================================================
class VirtualCursorSimulator {
    constructor(stageContainer, soundEngine) {
        this.stage = stageContainer;
        this.soundEngine = soundEngine;
        this.cursorEl = null;
        this.rippleEl = null;
        this.tickerOverlayEl = null;
        this.typingOverlayEl = null;
        this.toastBannerEl = null;

        this.currentX = 50; // percentage
        this.currentY = 50; // percentage
        this.targetX = 50;
        this.targetY = 50;
        this.isMoving = false;

        this.buildDOMElements();
    }

    buildDOMElements() {
        // 1. Virtual Mouse Cursor
        this.cursorEl = document.createElement('div');
        this.cursorEl.id = 'kineticVirtualCursor';
        this.cursorEl.className = 'absolute z-50 pointer-events-none transition-transform duration-300 ease-out opacity-0';
        this.cursorEl.style.left = '50%';
        this.cursorEl.style.top = '50%';
        this.cursorEl.style.transform = 'translate(-4px, -4px)';
        this.cursorEl.innerHTML = `
            <div class="relative flex items-start select-none">
                <svg class="w-7 h-7 text-white drop-shadow-[0_4px_12px_rgba(0,0,0,0.85)] filter" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 2l16 11.5-8.5 1.5-3.5 7z" stroke="#0f172a" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                <div id="cursorPulseDot" class="absolute -top-1 -left-1 w-3.5 h-3.5 rounded-full bg-cyan-400 opacity-80 blur-[2px] animate-ping"></div>
                <div id="cursorLabel" class="mr-2 mt-4 px-2 py-0.5 rounded-md bg-slate-950/90 text-cyan-300 font-mono text-[10px] font-bold border border-cyan-500/50 shadow-2xl backdrop-blur-md opacity-0 transition-opacity duration-200">
                    کلیک سیستم
                </div>
            </div>
        `;
        this.stage.appendChild(this.cursorEl);

        // 2. Click Ripple Ring
        this.rippleEl = document.createElement('div');
        this.rippleEl.id = 'kineticClickRipple';
        this.rippleEl.className = 'absolute z-40 pointer-events-none rounded-full border-2 border-cyan-400 opacity-0 transition-all duration-500';
        this.rippleEl.style.width = '20px';
        this.rippleEl.style.height = '20px';
        this.rippleEl.style.transform = 'translate(-50%, -50%) scale(0.5)';
        this.stage.appendChild(this.rippleEl);

        // 3. Dynamic Live Numeric Ticker Overlay
        this.tickerOverlayEl = document.createElement('div');
        this.tickerOverlayEl.id = 'kineticTickerOverlay';
        this.tickerOverlayEl.className = 'absolute z-45 pointer-events-none opacity-0 transition-all duration-300 transform -translate-x-1/2 -translate-y-1/2';
        this.stage.appendChild(this.tickerOverlayEl);

        // 4. Live Typing Field Overlay
        this.typingOverlayEl = document.createElement('div');
        this.typingOverlayEl.id = 'kineticTypingOverlay';
        this.typingOverlayEl.className = 'absolute z-45 pointer-events-none opacity-0 transition-all duration-300 transform -translate-x-1/2 -translate-y-1/2';
        this.stage.appendChild(this.typingOverlayEl);

        // 5. Toast Banner Overlay
        this.toastBannerEl = document.createElement('div');
        this.toastBannerEl.id = 'kineticToastBanner';
        this.toastBannerEl.className = 'absolute top-16 left-1/2 transform -translate-x-1/2 z-48 pointer-events-none opacity-0 transition-all duration-500';
        this.stage.appendChild(this.toastBannerEl);
    }

    reset() {
        this.cursorEl.classList.add('opacity-0');
        this.rippleEl.classList.add('opacity-0');
        this.tickerOverlayEl.classList.add('opacity-0');
        this.typingOverlayEl.classList.add('opacity-0');
        this.toastBannerEl.classList.add('opacity-0');
    }

    moveTo(targetX, targetY, duration = 800) {
        this.cursorEl.classList.remove('opacity-0');
        this.cursorEl.style.transition = `left ${duration}ms cubic-bezier(0.25, 1, 0.5, 1), top ${duration}ms cubic-bezier(0.25, 1, 0.5, 1)`;
        this.cursorEl.style.left = `${targetX}%`;
        this.cursorEl.style.top = `${targetY}%`;

        this.currentX = targetX;
        this.currentY = targetY;
    }

    clickAt(targetX, targetY, labelText = null, callback = null) {
        this.moveTo(targetX, targetY, 600);

        setTimeout(() => {
            // Sound
            if (this.soundEngine && this.soundEngine.playMouseClick) {
                this.soundEngine.playMouseClick();
            }

            // Depress cursor
            this.cursorEl.style.transform = 'translate(-4px, -4px) scale(0.85)';

            // Expand Ripple
            this.rippleEl.style.left = `${targetX}%`;
            this.rippleEl.style.top = `${targetY}%`;
            this.rippleEl.style.width = '80px';
            this.rippleEl.style.height = '80px';
            this.rippleEl.style.borderColor = '#06b6d4';
            this.rippleEl.style.boxShadow = '0 0 25px #06b6d4, inset 0 0 15px #10b981';
            this.rippleEl.classList.remove('opacity-0');
            this.rippleEl.style.transform = 'translate(-50%, -50%) scale(1.6)';

            // Label tooltip
            const labelEl = document.getElementById('cursorLabel');
            if (labelEl && labelText) {
                labelEl.innerText = labelText;
                labelEl.classList.remove('opacity-0');
                setTimeout(() => labelEl.classList.add('opacity-0'), 1800);
            }

            // Restore cursor scale
            setTimeout(() => {
                this.cursorEl.style.transform = 'translate(-4px, -4px) scale(1)';
                this.rippleEl.classList.add('opacity-0');
                this.rippleEl.style.transform = 'translate(-50%, -50%) scale(0.5)';
                if (callback) callback();
            }, 300);

        }, 620);
    }

    typeText(text, x, y, duration = 1200) {
        this.moveTo(x, y, 500);

        setTimeout(() => {
            this.typingOverlayEl.style.left = `${x}%`;
            this.typingOverlayEl.style.top = `${y - 6}%`;
            this.typingOverlayEl.classList.remove('opacity-0');

            const totalChars = text.length;
            const interval = Math.max(30, Math.floor(duration / totalChars));
            let charIndex = 0;

            const timer = setInterval(() => {
                charIndex++;
                const currentStr = text.substring(0, charIndex);
                this.typingOverlayEl.innerHTML = `
                    <div class="flex items-center gap-2 bg-slate-950/95 border border-cyan-500/80 px-3.5 py-1.5 rounded-xl shadow-2xl backdrop-blur-xl">
                        <span class="text-cyan-400 font-mono text-xs font-black tracking-wider">${currentStr}</span>
                        <span class="w-1.5 h-4 bg-cyan-400 animate-pulse inline-block"></span>
                    </div>
                `;

                if (this.soundEngine && this.soundEngine.playKeyboardType && charIndex % 2 === 0) {
                    this.soundEngine.playKeyboardType();
                }

                if (charIndex >= totalChars) {
                    clearInterval(timer);
                    setTimeout(() => {
                        this.typingOverlayEl.classList.add('opacity-0');
                    }, 2400);
                }
            }, interval);

        }, 520);
    }

    runTicker(startVal, endVal, duration = 1800, x = 50, y = 35, prefix = '', suffix = ' ریال', isAlert = false) {
        this.tickerOverlayEl.style.left = `${x}%`;
        this.tickerOverlayEl.style.top = `${y}%`;
        this.tickerOverlayEl.classList.remove('opacity-0');

        const startTime = performance.now();
        const toneClass = isAlert ? 'text-rose-400 border-rose-500/80 bg-rose-950/90 shadow-rose-900/60' : 'text-emerald-400 border-emerald-500/80 bg-slate-950/95 shadow-emerald-900/60';

        const updateTick = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(1, elapsed / duration);
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const currentVal = Math.round(startVal + (endVal - startVal) * easeOut);

            const formatted = new Intl.NumberFormat('fa-IR').format(currentVal);

            this.tickerOverlayEl.innerHTML = `
                <div class="flex items-center gap-2.5 px-4 py-2 rounded-2xl border ${toneClass} shadow-2xl backdrop-blur-xl animate-pulse">
                    <span class="text-xs font-bold text-slate-200">${prefix}</span>
                    <span class="font-mono text-base sm:text-xl font-black tracking-tight">${formatted}</span>
                    <span class="text-xs font-bold text-slate-400">${suffix}</span>
                </div>
            `;

            if (this.soundEngine && this.soundEngine.playNumberTick && Math.random() < 0.35) {
                this.soundEngine.playNumberTick();
            }

            if (progress < 1) {
                requestAnimationFrame(updateTick);
            } else {
                setTimeout(() => {
                    this.tickerOverlayEl.classList.add('opacity-0');
                }, 2800);
            }
        };

        requestAnimationFrame(updateTick);
    }

    showToastBanner(title, desc, tone = 'success', duration = 3000) {
        const isSuccess = tone === 'success';
        const toneStyle = isSuccess 
            ? 'bg-emerald-950/90 border-emerald-500/80 text-emerald-200' 
            : 'bg-indigo-950/90 border-indigo-500/80 text-indigo-200';
        const icon = isSuccess ? '✅' : '🚀';

        this.toastBannerEl.innerHTML = `
            <div class="flex items-center gap-3 px-4 py-2.5 rounded-2xl border ${toneStyle} shadow-2xl backdrop-blur-xl">
                <span class="text-lg">${icon}</span>
                <div class="text-right">
                    <div class="font-black text-xs text-white">${title}</div>
                    <div class="text-[11px] text-slate-300">${desc}</div>
                </div>
            </div>
        `;

        this.toastBannerEl.classList.remove('opacity-0');
        this.toastBannerEl.style.transform = 'translate(-50%, 0) scale(1)';

        setTimeout(() => {
            this.toastBannerEl.classList.add('opacity-0');
            this.toastBannerEl.style.transform = 'translate(-50%, -10px) scale(0.95)';
        }, duration);
    }
}

// =========================================================================
// SUB-ENGINE 2: HORMOZI / APPLE KINETIC WORD-BY-WORD TYPOGRAPHY
// =========================================================================
class KineticTypographyEngine {
    constructor(stageContainer) {
        this.stage = stageContainer;
        this.containerEl = null;
        this.words = [];
        this.activeWordIndex = -1;
        this.buildStage();
    }

    buildStage() {
        this.containerEl = document.createElement('div');
        this.containerEl.id = 'kineticWordStage';
        this.containerEl.className = 'absolute bottom-20 inset-x-4 sm:inset-x-12 z-35 pointer-events-none flex flex-wrap justify-center items-center gap-2 text-center';
        this.stage.appendChild(this.containerEl);
    }

    setSceneText(fullSentence, customTimings = null) {
        this.containerEl.innerHTML = '';
        if (!fullSentence) return;

        const rawWords = fullSentence.trim().split(/\s+/);
        this.words = rawWords.map((word, idx) => {
            const span = document.createElement('span');
            span.className = 'inline-block font-black text-sm sm:text-base md:text-xl text-slate-400 px-1 py-0.5 rounded transition-all duration-150 transform scale-100';
            span.innerText = word;

            // Highlight special trigger words
            const cleanWord = word.replace(/[،!؟:.]/g, '');
            const isPowerWord = ['جریمه', '۲۸۰', 'میلیون', 'ماده', '۲۲', 'باج', 'معتمد', 'رایگان', '۱', 'کلیک', 'کارپوشه', 'شناسه', 'شفافیت', 'صفر', 'کابوس', 'تست'].includes(cleanWord);
            if (isPowerWord) {
                span.dataset.powerWord = 'true';
            }

            this.containerEl.appendChild(span);

            return {
                text: word,
                cleanWord: cleanWord,
                isPowerWord: isPowerWord,
                element: span,
                startTime: customTimings ? (customTimings[idx] ? customTimings[idx].start : 0) : 0,
                endTime: customTimings ? (customTimings[idx] ? customTimings[idx].end : 0) : 0
            };
        });

        this.activeWordIndex = -1;
    }

    update(currentTime, totalDuration) {
        if (!this.words || this.words.length === 0) return;

        // If no explicit word timings were generated, calculate linearly based on duration
        const wordCount = this.words.length;
        const timePerWord = totalDuration / wordCount;
        const currentTargetIndex = Math.min(wordCount - 1, Math.floor(currentTime / timePerWord));

        if (currentTargetIndex !== this.activeWordIndex) {
            this.activeWordIndex = currentTargetIndex;

            this.words.forEach((w, idx) => {
                if (idx === this.activeWordIndex) {
                    // ACTIVE HORMOZI POP WORD
                    if (w.isPowerWord) {
                        w.element.className = 'inline-block font-black text-base sm:text-xl md:text-2xl text-amber-300 bg-amber-950/80 border border-amber-400 px-2 py-0.5 rounded-lg shadow-[0_0_25px_rgba(251,191,36,0.9)] transform scale-125 -translate-y-1 z-10 transition-all duration-100';
                    } else {
                        w.element.className = 'inline-block font-black text-base sm:text-lg md:text-xl text-white drop-shadow-[0_0_15px_rgba(255,255,255,0.85)] transform scale-115 -translate-y-0.5 z-10 transition-all duration-100';
                    }
                } else if (idx < this.activeWordIndex) {
                    // ALREADY SPOKEN (dimmed white)
                    w.element.className = 'inline-block font-bold text-xs sm:text-sm md:text-base text-slate-300/80 px-1 py-0.5 transition-all duration-200 transform scale-100';
                } else {
                    // UPCOMING WORD
                    w.element.className = 'inline-block font-medium text-xs sm:text-sm md:text-base text-slate-500/60 px-1 py-0.5 transition-all duration-200 transform scale-100';
                }
            });
        }
    }
}

// =========================================================================
// SUB-ENGINE 3: MICRO-SHOT CAMERA PACER (KEN BURNS & CRASH ZOOMS)
// =========================================================================
class MicroShotPacerEngine {
    constructor(stageImage) {
        this.image = stageImage;
        this.microShots = [];
        this.currentShot = null;
        this.currentShotIndex = -1;
        this.shakeRemaining = 0;
        this.shakeIntensity = 0;

        // Steady-cam breathing phase
        this.breathingPhase = 0;
    }

    setMicroShots(shots) {
        this.microShots = shots || [];
        this.currentShotIndex = -1;
        this.currentShot = null;
    }

    triggerShake(intensity = 6, durationFrames = 15) {
        this.shakeIntensity = intensity;
        this.shakeRemaining = durationFrames;
    }

    update(currentTime) {
        if (!this.microShots || this.microShots.length === 0) return;

        // Find active micro-shot
        let activeIdx = this.microShots.findIndex(s => currentTime >= s.start && currentTime < s.end);
        if (activeIdx === -1 && currentTime >= this.microShots[this.microShots.length - 1].end) {
            activeIdx = this.microShots.length - 1;
        }

        if (activeIdx !== -1 && activeIdx !== this.currentShotIndex) {
            this.currentShotIndex = activeIdx;
            this.currentShot = this.microShots[activeIdx];

            // Update HUD telemetry if present
            const hudShotText = document.getElementById('hudShotText');
            if (hudShotText && this.currentShot.label) {
                hudShotText.innerText = `${this.currentShot.label} // ${this.currentShot.zoom}x`;
            }
        }
    }

    renderTick() {
        if (!this.image || !this.currentShot) return;

        this.breathingPhase += 0.025;
        const breathScale = 1.0 + Math.sin(this.breathingPhase) * 0.008; // Subtle 0.8% organic breathing
        const breathY = Math.cos(this.breathingPhase * 0.7) * 2;

        let shakeX = 0;
        let shakeY = 0;
        if (this.shakeRemaining > 0) {
            shakeX = (Math.random() * 2 - 1) * this.shakeIntensity;
            shakeY = (Math.random() * 2 - 1) * this.shakeIntensity;
            this.shakeRemaining--;
        }

        const targetZoom = (this.currentShot.zoom || 1.15) * breathScale;
        const originX = this.currentShot.originX !== undefined ? this.currentShot.originX : 50;
        const originY = this.currentShot.originY !== undefined ? this.currentShot.originY : 50;

        this.image.style.transformOrigin = `${originX}% ${originY}%`;
        this.image.style.transform = `translate3d(${shakeX}px, ${shakeY + breathY}px, 0) scale(${targetZoom})`;
    }
}

// =========================================================================
// SUB-ENGINE 4: ATMOSPHERIC VIDEO MOTION ENGINE (LIGHT BEAMS, PARTICLES, LASER)
// =========================================================================
class AtmosphericVideoEngine {
    constructor(stageContainer) {
        this.stage = stageContainer;
        this.canvas = null;
        this.ctx = null;
        this.particles = [];
        this.laserLineY = 0;
        this.laserSpeed = 1.2;
        this.lightSweepAngle = 0;
        this.currentTone = 'indigo';

        this.initCanvas();
        this.initParticles();
    }

    initCanvas() {
        this.canvas = document.createElement('canvas');
        this.canvas.id = 'kineticAtmosphereCanvas';
        this.canvas.className = 'absolute inset-0 w-full h-full pointer-events-none z-22';
        this.stage.appendChild(this.canvas);
        this.ctx = this.canvas.getContext('2d');

        this.resize();
        window.addEventListener('resize', () => this.resize());
    }

    resize() {
        if (!this.canvas) return;
        this.canvas.width = this.canvas.offsetWidth || this.stage.clientWidth;
        this.canvas.height = this.canvas.offsetHeight || this.stage.clientHeight;
    }

    initParticles() {
        this.particles = [];
        for (let i = 0; i < 40; i++) {
            this.particles.push({
                x: Math.random() * 800,
                y: Math.random() * 500,
                size: 1 + Math.random() * 2.5,
                alpha: 0.15 + Math.random() * 0.45,
                vx: (Math.random() - 0.5) * 0.4,
                vy: -0.2 - Math.random() * 0.5,
                hue: Math.random() > 0.5 ? 240 : 160
            });
        }
    }

    setSceneMode(sceneIndex, sceneData) {
        if (sceneIndex === 0 || sceneIndex === 1) {
            this.currentTone = 'rose'; // Crisis tone
        } else if (sceneIndex === 2 || sceneIndex === 3) {
            this.currentTone = 'cyan'; // High-Tech scanning
        } else {
            this.currentTone = 'emerald'; // Victory & Success
        }
    }

    render() {
        if (!this.ctx || !this.canvas) return;
        const w = this.canvas.width;
        const h = this.canvas.height;
        if (w === 0 || h === 0) return;

        this.ctx.clearRect(0, 0, w, h);

        // 1. Dynamic Volumetric Light Sweep
        this.lightSweepAngle += 0.008;
        const sweepX = (Math.sin(this.lightSweepAngle) * 0.5 + 0.5) * w;

        const lightGrad = this.ctx.createRadialGradient(sweepX, 0, 10, sweepX, h * 0.8, w * 0.7);
        if (this.currentTone === 'rose') {
            lightGrad.addColorStop(0, 'rgba(239, 68, 68, 0.08)');
            lightGrad.addColorStop(1, 'rgba(239, 68, 68, 0)');
        } else if (this.currentTone === 'cyan') {
            lightGrad.addColorStop(0, 'rgba(6, 182, 212, 0.09)');
            lightGrad.addColorStop(1, 'rgba(6, 182, 212, 0)');
        } else {
            lightGrad.addColorStop(0, 'rgba(16, 185, 129, 0.08)');
            lightGrad.addColorStop(1, 'rgba(16, 185, 129, 0)');
        }

        this.ctx.fillStyle = lightGrad;
        this.ctx.fillRect(0, 0, w, h);

        // 2. High-Tech Continuous Scanning Laser Bar
        this.laserLineY += this.laserSpeed;
        if (this.laserLineY > h || this.laserLineY < 0) {
            this.laserSpeed *= -1;
        }

        const laserGrad = this.ctx.createLinearGradient(0, this.laserLineY - 12, 0, this.laserLineY + 12);
        const laserColor = this.currentTone === 'rose' ? '239, 68, 68' : (this.currentTone === 'cyan' ? '6, 182, 212' : '16, 185, 129');
        laserGrad.addColorStop(0, `rgba(${laserColor}, 0)`);
        laserGrad.addColorStop(0.5, `rgba(${laserColor}, 0.35)`);
        laserGrad.addColorStop(1, `rgba(${laserColor}, 0)`);

        this.ctx.fillStyle = laserGrad;
        this.ctx.fillRect(0, this.laserLineY - 8, w, 16);

        // 3. Floating Cinema Dust Motes (Bokeh Drift)
        this.ctx.save();
        for (let p of this.particles) {
            p.x += p.vx;
            p.y += p.vy;

            if (p.y < 0) p.y = h;
            if (p.x < 0) p.x = w;
            if (p.x > w) p.x = 0;

            this.ctx.beginPath();
            this.ctx.arc((p.x / 800) * w, (p.y / 500) * h, p.size, 0, Math.PI * 2);
            this.ctx.fillStyle = `hsla(${p.hue}, 80%, 75%, ${p.alpha})`;
            this.ctx.shadowColor = `hsla(${p.hue}, 80%, 60%, 0.8)`;
            this.ctx.shadowBlur = 8;
            this.ctx.fill();
        }
        this.ctx.restore();
    }
}

// Global Export
if (typeof window !== 'undefined') {
    window.NeptuneKineticMovieEngine = NeptuneKineticMovieEngine;
}
