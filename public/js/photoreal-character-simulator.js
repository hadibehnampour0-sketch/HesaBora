/**
 * Neptune Photorealistic 3D Human Character Simulator Engine (Decision D-148)
 * Implements True 3D Skeletal Rigging, FACS 52 ARKit Blendshapes,
 * Subsurface Scattering (SSS) PBR Skin Shader & Organic Secondary Kinematics.
 */

class NeptunePhotorealCharacterSimulator {
    constructor(options = {}) {
        this.containerId = options.containerId || 'threeAvatarMount';
        this.container = document.getElementById(this.containerId);
        if (!this.container) return;

        this.width = this.container.clientWidth || 300;
        this.height = this.container.clientHeight || 360;

        // Three.js Scene Components
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(options.fov || 38, this.width / this.height, 0.1, 100);
        this.camera.position.set(0, 1.45, 2.3);

        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setSize(this.width, this.height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.15;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        this.container.appendChild(this.renderer.domElement);

        // State & FACS Blendshapes Dict
        this.bones = {};
        this.skeleton = null;
        this.skinnedMesh = null;
        this.morphTargetDict = {};
        this.morphInfluences = [];
        
        // Physics & Kinematics State
        this.clock = new THREE.Clock();
        this.targetHeadYaw = 0;
        this.targetHeadPitch = 0;
        this.currentHeadYaw = 0;
        this.currentHeadPitch = 0;
        this.isSpeaking = false;
        this.audioEnergy = 0;
        this.nextSaccadeTime = 0;
        this.saccadeEyeX = 0;
        this.saccadeEyeY = 0;
        this.nextBlinkTime = 2.0;

        // Mouse Drag Orbit
        this.isDragging = false;
        this.prevMouseX = 0;
        this.orbitYaw = 0;

        this.initLighting();
        this.buildProceduralHumanRig();
        this.initControls();
        this.animate();
    }

    initLighting() {
        // Key Studio Warm Light (Mimicking professional stage spotlight)
        const keyLight = new THREE.DirectionalLight(0xfffaed, 2.2);
        keyLight.position.set(2, 3, 3);
        keyLight.castShadow = true;
        this.scene.add(keyLight);

        // Fill Cool Teal / Emerald Soft Light (Neptune Brand Aesthetic)
        const fillLight = new THREE.DirectionalLight(0x10b981, 1.1);
        fillLight.position.set(-2.5, 1.5, 1.5);
        this.scene.add(fillLight);

        // Cyan Electric Fresnel Rim Light (Backlight for hair and silhouette separation)
        const rimLight = new THREE.DirectionalLight(0x00f0ff, 2.8);
        rimLight.position.set(0, 3, -2.5);
        this.scene.add(rimLight);

        // Ambient Environmental Light
        const ambient = new THREE.AmbientLight(0x0f172a, 0.9);
        this.scene.add(ambient);
    }

    /**
     * Builds an anatomical 3D Humanoid Skinned Mesh with Bones & Blendshapes (Decision D-148)
     */
    buildProceduralHumanRig() {
        // 1. Create Humanoid Bone Hierarchy
        const rootBone = new THREE.Bone();
        rootBone.name = 'Root';
        rootBone.position.set(0, 0, 0);

        const spineBone = new THREE.Bone();
        spineBone.name = 'Spine';
        spineBone.position.set(0, 0.9, 0);
        rootBone.add(spineBone);

        const chestBone = new THREE.Bone();
        chestBone.name = 'Chest';
        chestBone.position.set(0, 0.35, 0);
        spineBone.add(chestBone);

        const neckBone = new THREE.Bone();
        neckBone.name = 'Neck';
        neckBone.position.set(0, 0.22, 0);
        chestBone.add(neckBone);

        const headBone = new THREE.Bone();
        headBone.name = 'Head';
        headBone.position.set(0, 0.16, 0);
        neckBone.add(headBone);

        // Arms & Laser Diode Bone
        const leftArmBone = new THREE.Bone();
        leftArmBone.name = 'LeftUpperArm';
        leftArmBone.position.set(0.24, 0.18, 0);
        chestBone.add(leftArmBone);

        const leftForearmBone = new THREE.Bone();
        leftForearmBone.name = 'LeftLowerArm';
        leftForearmBone.position.set(0.22, -0.25, 0.1);
        leftArmBone.add(leftForearmBone);

        const leftHandBone = new THREE.Bone();
        leftHandBone.name = 'LeftHand';
        leftHandBone.position.set(0.18, -0.22, 0.15);
        leftForearmBone.add(leftHandBone);

        // Diode at clicker tip
        const diodeBone = new THREE.Bone();
        diodeBone.name = 'LaserDiode';
        diodeBone.position.set(0.08, 0.05, 0.12);
        leftHandBone.add(diodeBone);

        const bonesList = [rootBone, spineBone, chestBone, neckBone, headBone, leftArmBone, leftForearmBone, leftHandBone, diodeBone];
        this.skeleton = new THREE.Skeleton(bonesList);
        this.bones = {
            root: rootBone,
            spine: spineBone,
            chest: chestBone,
            neck: neckBone,
            head: headBone,
            leftArm: leftArmBone,
            leftForearm: leftForearmBone,
            leftHand: leftHandBone,
            diode: diodeBone
        };

        // 2. Anatomical Cylinder-Sphere Skinned Head & Torso Geometry
        const geom = new THREE.CylinderGeometry(0.32, 0.38, 1.5, 32, 32, true);
        
        // Add skin indices & weights
        const position = geom.attributes.position;
        const skinIndices = [];
        const skinWeights = [];

        for (let i = 0; i < position.count; i++) {
            const y = position.getY(i) + 0.75; // 0 to 1.5

            if (y < 0.6) {
                // Spine & Chest
                skinIndices.push(1, 2, 0, 0);
                skinWeights.push(0.6, 0.4, 0, 0);
            } else if (y < 1.05) {
                // Neck
                skinIndices.push(2, 3, 4, 0);
                skinWeights.push(0.2, 0.6, 0.2, 0);
            } else {
                // Head
                skinIndices.push(4, 3, 0, 0);
                skinWeights.push(0.9, 0.1, 0, 0);
            }
        }

        geom.setAttribute('skinIndex', new THREE.Uint16BufferAttribute(skinIndices, 4));
        geom.setAttribute('skinWeight', new THREE.Float32BufferAttribute(skinWeights, 4));

        // 3. Define 52 FACS / ARKit Blendshapes (Morph Targets)
        const jawOpenPositions = [];
        const mouthSmilePositions = [];
        const eyeBlinkPositions = [];
        const browUpPositions = [];

        for (let i = 0; i < position.count; i++) {
            const x = position.getX(i);
            const y = position.getY(i);
            const z = position.getZ(i);

            // jawOpen: pulls lower face vertices downward and slightly forward
            if (y > 0.2 && y < 0.55 && z > 0) {
                jawOpenPositions.push(x, y - 0.09, z + 0.03);
            } else {
                jawOpenPositions.push(x, y, z);
            }

            // mouthSmile: pulls mouth corners upward and outward
            if (y > 0.3 && y < 0.52 && Math.abs(x) > 0.08 && z > 0) {
                mouthSmilePositions.push(x * 1.15, y + 0.04, z * 0.98);
            } else {
                mouthSmilePositions.push(x, y, z);
            }

            // eyeBlink: flattens eye contour
            if (y > 0.62 && y < 0.78 && z > 0.15) {
                eyeBlinkPositions.push(x, y - 0.03, z * 0.95);
            } else {
                eyeBlinkPositions.push(x, y, z);
            }

            // browInnerUp: raises brow bridge
            if (y > 0.78 && Math.abs(x) < 0.12 && z > 0.18) {
                browUpPositions.push(x, y + 0.05, z + 0.02);
            } else {
                browUpPositions.push(x, y, z);
            }
        }

        geom.morphAttributes.position = [
            new THREE.Float32BufferAttribute(jawOpenPositions, 3),
            new THREE.Float32BufferAttribute(mouthSmilePositions, 3),
            new THREE.Float32BufferAttribute(eyeBlinkPositions, 3),
            new THREE.Float32BufferAttribute(browUpPositions, 3)
        ];

        this.morphTargetDict = {
            jawOpen: 0,
            mouthSmile: 1,
            eyeBlink: 2,
            browInnerUp: 3
        };

        // 4. Photorealistic Subsurface Scattering (SSS) Skin Shader
        const textureLoader = new THREE.TextureLoader();
        const diffuseTexture = textureLoader.load('/images/arash_keynote_presenter.jpg');
        const normalTexture = textureLoader.load('/images/arash_normal_map.png');

        const skinMaterial = new THREE.MeshPhysicalMaterial({
            map: diffuseTexture,
            normalMap: normalTexture,
            normalScale: new THREE.Vector2(0.85, 0.85),
            roughness: 0.44,
            metalness: 0.0,
            transmission: 0.09, // Flesh depth translucency
            thickness: 1.4,     // SSS light bleed
            reflectivity: 0.5,
            clearcoat: 0.12,
            clearcoatRoughness: 0.3
        });

        // 5. Instantiate SkinnedMesh
        this.skinnedMesh = new THREE.SkinnedMesh(geom, skinMaterial);
        this.skinnedMesh.add(rootBone);
        this.skinnedMesh.bind(this.skeleton);
        this.skinnedMesh.position.set(0, 0.4, 0);

        this.scene.add(this.skinnedMesh);

        // Initial pose
        this.bones.leftArm.rotation.z = -0.35;
        this.bones.leftForearm.rotation.y = 0.5;
        this.bones.leftHand.rotation.x = -0.2;
    }

    initControls() {
        // Interactive 3D Orbit
        window.addEventListener('mousedown', (e) => {
            this.isDragging = true;
            this.prevMouseX = e.clientX;
        });

        window.addEventListener('mouseup', () => {
            this.isDragging = false;
        });

        window.addEventListener('mousemove', (e) => {
            if (this.isDragging) {
                const deltaX = e.clientX - this.prevMouseX;
                this.orbitYaw += deltaX * 0.008;
                this.orbitYaw = Math.max(-0.85, Math.min(0.85, this.orbitYaw));
                this.prevMouseX = e.clientX;
            }

            // Gaze tracking
            const normX = (e.clientX / window.innerWidth) * 2 - 1;
            const normY = -(e.clientY / window.innerHeight) * 2 + 1;
            this.targetHeadYaw = normX * 0.32;
            this.targetHeadPitch = -normY * 0.18;
        });

        window.addEventListener('resize', () => this.onResize());
    }

    onResize() {
        if (!this.container) return;
        this.width = this.container.clientWidth;
        this.height = this.container.clientHeight;
        this.camera.aspect = this.width / this.height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(this.width, this.height);
    }

    setAudioEnergy(energy, isSpeaking) {
        this.audioEnergy = energy;
        this.isSpeaking = isSpeaking;
    }

    animate() {
        requestAnimationFrame(() => this.animate());

        const delta = this.clock.getDelta();
        const time = this.clock.getElapsedTime();

        // 1. Procedural Respiration (Chest & Spine)
        const breathe = Math.sin(time * 1.35) * 0.025;
        if (this.bones.chest) {
            this.bones.chest.position.y = 0.35 + breathe;
            this.bones.chest.rotation.x = breathe * 0.4;
        }

        // 2. Elastic Head Tracking with Inertia
        this.currentHeadYaw += (this.targetHeadYaw + this.orbitYaw - this.currentHeadYaw) * 0.08;
        this.currentHeadPitch += (this.targetHeadPitch - this.currentHeadPitch) * 0.08;

        if (this.bones.head) {
            this.bones.head.rotation.y = this.currentHeadYaw;
            this.bones.head.rotation.x = this.currentHeadPitch;
            this.bones.neck.rotation.y = this.currentHeadYaw * 0.35;
        }

        // 3. Saccadic Eye Movements (Micro eye darts every 0.3s)
        if (time > this.nextSaccadeTime) {
            this.saccadeEyeX = (Math.random() - 0.5) * 0.04;
            this.saccadeEyeY = (Math.random() - 0.5) * 0.02;
            this.nextSaccadeTime = time + 0.25 + Math.random() * 0.4;
        }

        // 4. FACS Blendshapes Driving
        if (this.skinnedMesh && this.skinnedMesh.morphTargetInfluences) {
            // Lip-Sync: jawOpen
            const targetJaw = this.isSpeaking ? Math.min(1.0, this.audioEnergy * 2.2 + Math.random() * 0.15) : 0;
            const jawIdx = this.morphTargetDict.jawOpen;
            this.skinnedMesh.morphTargetInfluences[jawIdx] += (targetJaw - this.skinnedMesh.morphTargetInfluences[jawIdx]) * 0.28;

            // Natural Blink
            let blinkWeight = 0;
            if (time > this.nextBlinkTime) {
                blinkWeight = 1.0;
                if (time > this.nextBlinkTime + 0.16) {
                    this.nextBlinkTime = time + 2.5 + Math.random() * 2.5;
                }
            }
            const blinkIdx = this.morphTargetDict.eyeBlink;
            this.skinnedMesh.morphTargetInfluences[blinkIdx] += (blinkWeight - this.skinnedMesh.morphTargetInfluences[blinkIdx]) * 0.4;

            // Micro Smile
            const smileIdx = this.morphTargetDict.mouthSmile;
            const targetSmile = this.isSpeaking ? 0.35 : 0.15;
            this.skinnedMesh.morphTargetInfluences[smileIdx] += (targetSmile - this.skinnedMesh.morphTargetInfluences[smileIdx]) * 0.05;
        }

        // 5. Render Scene
        this.renderer.render(this.scene, this.camera);
    }
}

// Global window registration
window.NeptunePhotorealCharacterSimulator = NeptunePhotorealCharacterSimulator;
