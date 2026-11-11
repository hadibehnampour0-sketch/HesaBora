/**
 * Neptune ERP — Interactive 3D WebGL Presenter Engine (Decision D-144)
 * Hardware-accelerated 3D volumetric avatar using Three.js, Depth Displacement & PBR Shaders.
 */

class Neptune3DPresenterEngine {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.warn(`[Neptune3D] Container #${containerId} not found.`);
            return;
        }

        this.options = Object.assign({
            cutoutUrl: '/images/arash_presenter_cutout.png',
            depthMapUrl: '/images/arash_depth_map.png',
            normalMapUrl: '/images/arash_normal_map.png',
            depthScale: 0.55,
            width: 320,
            height: 480,
            onLaserEmitterUpdate: null
        }, options);

        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.mesh = null;
        this.material = null;
        this.lights = {};
        
        // Interaction states
        this.isDragging = false;
        this.previousMousePosition = { x: 0, y: 0 };
        this.targetRotation = { x: 0, y: 0 };
        this.currentRotation = { x: 0, y: 0 };
        this.mouseParallax = { x: 0, y: 0 };
        
        // Animation uniforms & parameters
        this.clock = new THREE.Clock();
        this.jawOpen = 0.0;
        this.targetJawOpen = 0.0;
        this.headTilt = 0.0;
        this.targetHeadTilt = 0.0;
        this.blinkState = 0.0;
        this.lastBlinkTime = 0;
        this.nextBlinkInterval = 4.0;
        
        // Emitter position in 3D world space (relative to container)
        this.laserEmitterScreenPos = { x: 0, y: 0 };

        this.init();
    }

    init() {
        if (typeof THREE === 'undefined') {
            console.error('[Neptune3D] Three.js is not loaded.');
            return;
        }

        // 1. Create WebGL Canvas
        this.canvas = document.createElement('canvas');
        this.canvas.className = 'w-full h-full cursor-grab active:cursor-grabbing select-none';
        this.canvas.style.display = 'block';
        this.container.innerHTML = '';
        this.container.appendChild(this.canvas);

        const rect = this.container.getBoundingClientRect();
        const width = rect.width || this.options.width;
        const height = rect.height || this.options.height;

        // 2. Setup Three.js Scene & Perspective Camera
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(38, width / height, 0.1, 100);
        this.camera.position.set(0, -0.05, 5.2);

        // 3. Renderer with Premultiplied Alpha & Anti-aliasing
        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            alpha: true,
            antialias: true,
            powerPreference: 'high-performance'
        });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

        // 4. Load Textures
        const textureLoader = new THREE.TextureLoader();
        const diffuseTex = textureLoader.load(this.options.cutoutUrl);
        const depthTex = textureLoader.load(this.options.depthMapUrl);
        const normalTex = textureLoader.load(this.options.normalMapUrl);

        diffuseTex.generateMipmaps = true;
        diffuseTex.minFilter = THREE.LinearMipmapLinearFilter;

        // 5. Custom 3D Volumetric Depth-Displacement Shader Material
        const vertexShader = `
            uniform sampler2D uDepthMap;
            uniform float uDepthScale;
            uniform float uMouthOpen;
            uniform float uBlink;
            uniform float uTime;
            
            varying vec2 vUv;
            varying vec3 vNormal;
            varying vec3 vWorldPosition;
            varying float vDepth;
            
            void main() {
                vUv = uv;
                float depthVal = texture2D(uDepthMap, uv).r;
                vDepth = depthVal;
                
                vec3 pos = position;
                
                // Volumetric Z-extrusion along depth gradient
                pos.z += depthVal * uDepthScale;
                
                // Real-time Audio-driven 3D Jaw Drop & Mouth Opening
                // Mouth region: uv.y ~ [0.77, 0.85], uv.x ~ [0.44, 0.56]
                if (uv.y > 0.76 && uv.y < 0.86 && abs(uv.x - 0.50) < 0.07) {
                    float mouthMask = smoothstep(0.07, 0.0, abs(uv.x - 0.50)) *
                                      smoothstep(0.76, 0.81, uv.y) * 
                                      smoothstep(0.86, 0.81, uv.y);
                    pos.y -= uMouthOpen * 0.08 * mouthMask;
                    pos.z -= uMouthOpen * 0.04 * mouthMask; // Inset mouth cavity in 3D
                }
                
                // Subtle organic 3D breathing displacement across torso
                if (uv.y > 0.25 && uv.y < 0.72) {
                    float breathe = sin(uTime * 1.35) * 0.025;
                    pos.z += breathe * depthVal;
                }
                
                vec4 worldPos = modelMatrix * vec4(pos, 1.0);
                vWorldPosition = worldPos.xyz;
                gl_Position = projectionMatrix * viewMatrix * worldPos;
            }
        `;

        const fragmentShader = `
            uniform sampler2D uTexture;
            uniform sampler2D uNormalMap;
            uniform vec3 uKeyLightColor;
            uniform vec3 uKeyLightPos;
            uniform vec3 uRimLightColor;
            uniform vec3 uRimLightPos;
            uniform float uBlink;
            
            varying vec2 vUv;
            varying vec3 vWorldPosition;
            varying float vDepth;
            
            void main() {
                vec4 texColor = texture2D(uTexture, vUv);
                if (texColor.a < 0.03) discard;
                
                // Realistic Eyelid Blink shader darkening over eye region (uv.y ~ [0.84, 0.88], uv.x ~ [0.42, 0.58])
                if (uBlink > 0.01 && uv.y > 0.84 && uv.y < 0.88 && abs(uv.x - 0.50) < 0.09) {
                    float eyeMask = smoothstep(0.09, 0.0, abs(uv.x - 0.50)) * smoothstep(0.84, 0.86, uv.y) * smoothstep(0.88, 0.86, uv.y);
                    texColor.rgb = mix(texColor.rgb, vec3(0.08, 0.07, 0.06), eyeMask * uBlink * 0.95);
                }
                
                // Normal map lookup
                vec3 normal = texture2D(uNormalMap, vUv).rgb * 2.0 - 1.0;
                normal = normalize(normal);
                
                // Key Studio Light (Warm Directional)
                vec3 keyDir = normalize(uKeyLightPos - vWorldPosition);
                float diff = max(dot(normal, keyDir), 0.0);
                vec3 diffuse = diff * uKeyLightColor * 0.85;
                
                // Cyber Neon-Cyan Rim Light (Fresnel grazing reflection)
                vec3 viewDir = normalize(cameraPosition - vWorldPosition);
                float fresnel = 1.0 - max(dot(viewDir, normal), 0.0);
                fresnel = pow(fresnel, 2.8);
                vec3 rim = fresnel * uRimLightColor * 1.5;
                
                // Ambient Base Fill
                vec3 ambient = vec3(0.22, 0.25, 0.30);
                
                vec3 finalRgb = texColor.rgb * (ambient + diffuse) + rim;
                gl_FragColor = vec4(finalRgb, texColor.a);
            }
        `;

        this.material = new THREE.ShaderMaterial({
            vertexShader,
            fragmentShader,
            uniforms: {
                uTexture: { value: diffuseTex },
                uDepthMap: { value: depthTex },
                uNormalMap: { value: normalTex },
                uDepthScale: { value: this.options.depthScale },
                uMouthOpen: { value: 0.0 },
                uBlink: { value: 0.0 },
                uTime: { value: 0.0 },
                uKeyLightColor: { value: new THREE.Color(0xfff7ed) },
                uKeyLightPos: { value: new THREE.Vector3(2.5, 3.5, 4.0) },
                uRimLightColor: { value: new THREE.Color(0x00f0ff) },
                uRimLightPos: { value: new THREE.Vector3(-3.5, 2.0, -1.0) }
            },
            transparent: true,
            side: THREE.DoubleSide
        });

        // 6. High-density Plane Mesh (128x128 vertices for pristine curved depth)
        const geometry = new THREE.PlaneGeometry(2.35, 4.55, 128, 128);
        this.mesh = new THREE.Mesh(geometry, this.material);
        this.scene.add(this.mesh);

        // 7. Clicker Diode Indicator in 3D Space
        const diodeGeo = new THREE.SphereGeometry(0.04, 16, 16);
        const diodeMat = new THREE.MeshBasicMaterial({ color: 0x10b981 });
        this.clickerDiodeMesh = new THREE.Mesh(diodeGeo, diodeMat);
        // Position relative to Arash's hand holding the laser presenter remote
        this.clickerDiodeMesh.position.set(0.55, -0.45, 0.42);
        this.mesh.add(this.clickerDiodeMesh);

        // 8. Setup Interactive Events (Mouse / Touch Orbit)
        this.setupInteractions();

        // 9. Start Render Loop
        this.animate = this.animate.bind(this);
        requestAnimationFrame(this.animate);

        window.addEventListener('resize', () => this.onResize());
        console.log('[Neptune3D] Interactive 3D WebGL Avatar successfully initialized.');
    }

    setupInteractions() {
        const onDown = (e) => {
            this.isDragging = true;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            this.previousMousePosition = { x: clientX, y: clientY };
        };

        const onMove = (e) => {
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            if (this.isDragging) {
                const deltaX = clientX - this.previousMousePosition.x;
                const deltaY = clientY - this.previousMousePosition.y;

                // Orbit rotation limits: Y +/- 45 deg, X +/- 15 deg
                this.targetRotation.y += deltaX * 0.007;
                this.targetRotation.x += deltaY * 0.005;

                this.targetRotation.y = Math.max(-0.75, Math.min(0.75, this.targetRotation.y));
                this.targetRotation.x = Math.max(-0.25, Math.min(0.25, this.targetRotation.x));

                this.previousMousePosition = { x: clientX, y: clientY };
            } else {
                // Subtle gaze parallax following cursor across window
                const normX = (clientX / window.innerWidth) * 2 - 1;
                const normY = -(clientY / window.innerHeight) * 2 + 1;
                this.mouseParallax.x = normX * 0.12;
                this.mouseParallax.y = normY * 0.08;
            }
        };

        const onUp = () => {
            this.isDragging = false;
        };

        this.canvas.addEventListener('mousedown', onDown);
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);

        this.canvas.addEventListener('touchstart', onDown, { passive: true });
        window.addEventListener('touchmove', onMove, { passive: true });
        window.addEventListener('touchend', onUp);
    }

    onResize() {
        if (!this.container || !this.renderer || !this.camera) return;
        const rect = this.container.getBoundingClientRect();
        const width = rect.width || this.options.width;
        const height = rect.height || this.options.height;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }

    updateSpeechAudio(overallVolume, lowEnergy, highEnergy) {
        if (overallVolume > 0.08) {
            this.targetJawOpen = Math.min(1.0, (lowEnergy / 255.0) * 1.4);
            this.targetHeadTilt = Math.sin(this.clock.getElapsedTime() * 8.0) * (overallVolume * 0.04);
        } else {
            this.targetJawOpen = 0.0;
            this.targetHeadTilt = 0.0;
        }
    }

    getLaserEmitterScreenPosition() {
        if (!this.clickerDiodeMesh || !this.camera || !this.renderer) return null;
        
        const worldPos = new THREE.Vector3();
        this.clickerDiodeMesh.getWorldPosition(worldPos);
        
        // Project 3D coordinate to 2D screen coordinate in container
        worldPos.project(this.camera);
        
        const rect = this.canvas.getBoundingClientRect();
        const x = (worldPos.x * 0.5 + 0.5) * rect.width + rect.left;
        const y = (-(worldPos.y * 0.5) + 0.5) * rect.height + rect.top;
        
        return { x, y };
    }

    animate() {
        requestAnimationFrame(this.animate);

        const delta = this.clock.getDelta();
        const elapsedTime = this.clock.getElapsedTime();

        if (this.material && this.material.uniforms) {
            this.material.uniforms.uTime.value = elapsedTime;

            // Smooth Jaw LERP
            this.jawOpen += (this.targetJawOpen - this.jawOpen) * 0.3;
            this.material.uniforms.uMouthOpen.value = this.jawOpen;

            // Organic Blinking logic
            if (elapsedTime - this.lastBlinkTime > this.nextBlinkInterval) {
                this.lastBlinkTime = elapsedTime;
                this.nextBlinkInterval = 3.2 + Math.random() * 2.8;
                this.blinkState = 1.0;
            }

            if (this.blinkState > 0.0) {
                this.blinkState -= delta * 6.0;
                if (this.blinkState < 0.0) this.blinkState = 0.0;
            }
            this.material.uniforms.uBlink.value = this.blinkState;
        }

        // 3D Stance Smooth Spring Damping
        if (!this.isDragging) {
            // Gradually return toward center with mouse parallax influence
            this.targetRotation.x += (this.mouseParallax.y - this.targetRotation.x) * 0.05;
            this.targetRotation.y += (this.mouseParallax.x - this.targetRotation.y) * 0.05;
        }

        this.currentRotation.x += (this.targetRotation.x - this.currentRotation.x) * 0.12;
        this.currentRotation.y += (this.targetRotation.y - this.currentRotation.y) * 0.12;

        if (this.mesh) {
            this.mesh.rotation.x = this.currentRotation.x;
            this.mesh.rotation.y = this.currentRotation.y;
            this.mesh.rotation.z = Math.sin(elapsedTime * 0.8) * 0.015 + this.targetHeadTilt;
        }

        if (this.renderer && this.scene && this.camera) {
            this.renderer.render(this.scene, this.camera);
        }
    }
}
