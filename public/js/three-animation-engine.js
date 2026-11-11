/**
 * Neptune 3D Animation Production & Motion Choreography Engine (Decision D-150)
 * Provides Procedural Keyframe Clip Generation, Animation Mixer FSM,
 * Biped Locomotion Biomechanics, Cinematic 3D Camera Sequencer & Exploded Motion Graphics.
 */

class Neptune3DAnimationEngine {
    constructor(characterSimulator) {
        this.sim = characterSimulator;
        this.mixer = null;
        this.actions = {};
        this.currentActionName = 'idle';
        this.clips = {};
        this.cameraTimeline = null;
        this.hologramGroup = null;

        if (this.sim && this.sim.skinnedMesh) {
            this.initMixer();
            this.buildStandardClips();
            this.play('idle');
        }
    }

    /**
     * Initializes the Three.js AnimationMixer on the SkinnedMesh root
     */
    initMixer() {
        if (!this.sim || !this.sim.skinnedMesh) return;
        this.mixer = new THREE.AnimationMixer(this.sim.skinnedMesh);
    }

    /**
     * Builds Procedural AnimationClips with Vector, Quaternion and Morph Keyframe Tracks
     */
    buildStandardClips() {
        if (!this.mixer) return;

        // 1. Idle Clip (Subtle breathing, weight shift, organic gaze)
        this.clips['idle'] = this.createIdleClip();

        // 2. Walk Clip (Full Biped Locomotion: Hip bounce, sway, spine counter-rotation, arm swing)
        this.clips['walk'] = this.createWalkClip();

        // 3. Explain Clip (Open hand teaching, chest expansion, welcoming gesture)
        this.clips['explain'] = this.createExplainClip();

        // 4. Point Laser Clip (Right arm raised, diode aiming, focused stance)
        this.clips['point'] = this.createPointClip();

        // 5. Shocked / Crisis Clip (Sudden flinch, defensive arm pose, head pull back)
        this.clips['shocked'] = this.createShockedClip();

        // 6. Celebrate / Victory Clip (Confident posture, fist pump, triumphant smile)
        this.clips['celebrate'] = this.createCelebrateClip();

        // Register actions in mixer
        Object.keys(this.clips).forEach(name => {
            const clip = this.clips[name];
            const action = this.mixer.clipAction(clip);
            action.clampWhenFinished = false;
            action.loop = THREE.LoopRepeat;
            this.actions[name] = action;
        });
    }

    /**
     * 1. Idle Animation Clip (Duration: 3.0s)
     */
    createIdleClip() {
        const duration = 3.0;
        const times = [0, 0.75, 1.5, 2.25, 3.0];

        // Chest respiration position track
        const chestPosValues = [
            0, 0.35, 0,
            0, 0.375, 0,
            0, 0.35, 0,
            0, 0.375, 0,
            0, 0.35, 0
        ];
        const chestPosTrack = new THREE.VectorKeyframeTrack('Chest.position', times, chestPosValues);

        // Subtle Spine Sway
        const spineRotQ = [];
        [0, 0.015, 0, -0.015, 0].forEach(angle => {
            const q = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), angle);
            spineRotQ.push(q.x, q.y, q.z, q.w);
        });
        const spineTrack = new THREE.QuaternionKeyframeTrack('Spine.quaternion', times, spineRotQ);

        // Relaxed Arm Position
        const lArmQ = [];
        const rArmQ = [];
        times.forEach(t => {
            const qL = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 0, 1), 0.12 + Math.sin(t) * 0.02);
            const qR = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 0, 1), -0.12 - Math.sin(t) * 0.02);
            lArmQ.push(qL.x, qL.y, qL.z, qL.w);
            rArmQ.push(qR.x, qR.y, rArmQ.z, qR.w);
        });
        const lArmTrack = new THREE.QuaternionKeyframeTrack('LeftArm.quaternion', times, lArmQ);
        const rArmTrack = new THREE.QuaternionKeyframeTrack('RightArm.quaternion', times, rArmQ);

        return new THREE.AnimationClip('idle', duration, [chestPosTrack, spineTrack, lArmTrack, rArmTrack]);
    }

    /**
     * 2. Full Biped Locomotion Walk Cycle (Duration: 1.2s)
     * Mathematical Kinematics: Hip oscillation, lateral sway, opposing shoulder swing
     */
    createWalkClip() {
        const duration = 1.2;
        const fps = 24;
        const frameCount = Math.floor(duration * fps);
        const times = [];
        
        const rootPosValues = [];
        const spineRotValues = [];
        const lArmRotValues = [];
        const rArmRotValues = [];

        for (let i = 0; i <= frameCount; i++) {
            const t = (i / frameCount) * duration;
            times.push(t);

            const phase = (t / duration) * Math.PI * 2;

            // Hip drop and bounce: Frequency = 2 * step frequency
            const hipY = -0.04 * Math.abs(Math.sin(phase));
            const hipX = 0.03 * Math.sin(phase);
            rootPosValues.push(hipX, hipY, 0);

            // Spine counter-rotation to balance pelvis
            const spineAngleY = -0.08 * Math.sin(phase);
            const spineAngleZ = 0.04 * Math.cos(phase);
            const qSpine = new THREE.Quaternion().setFromEuler(new THREE.Euler(0.04, spineAngleY, spineAngleZ));
            spineRotValues.push(qSpine.x, qSpine.y, qSpine.z, qSpine.w);

            // Arm pendulum swing (Right arm opposes Left foot)
            const armSwingAngleL = 0.35 * Math.sin(phase);
            const armSwingAngleR = -0.35 * Math.sin(phase);

            const qArmL = new THREE.Quaternion().setFromEuler(new THREE.Euler(armSwingAngleL, 0, 0.1));
            const qArmR = new THREE.Quaternion().setFromEuler(new THREE.Euler(armSwingAngleR, 0, -0.1));

            lArmRotValues.push(qArmL.x, qArmL.y, qArmL.z, qArmL.w);
            rArmRotValues.push(qArmR.x, qArmR.y, qArmR.z, qArmR.w);
        }

        const rootTrack = new THREE.VectorKeyframeTrack('Root.position', times, rootPosValues);
        const spineTrack = new THREE.QuaternionKeyframeTrack('Spine.quaternion', times, spineRotValues);
        const lArmTrack = new THREE.QuaternionKeyframeTrack('LeftArm.quaternion', times, lArmRotValues);
        const rArmTrack = new THREE.QuaternionKeyframeTrack('RightArm.quaternion', times, rArmRotValues);

        return new THREE.AnimationClip('walk', duration, [rootTrack, spineTrack, lArmTrack, rArmTrack]);
    }

    /**
     * 3. Explain / Presentation Clip (Duration: 2.0s)
     */
    createExplainClip() {
        const duration = 2.0;
        const times = [0, 0.5, 1.0, 1.5, 2.0];

        // Chest proud stance
        const chestPosValues = [0, 0.36, 0, 0, 0.38, 0, 0, 0.37, 0, 0, 0.38, 0, 0, 0.36, 0];
        const chestTrack = new THREE.VectorKeyframeTrack('Chest.position', times, chestPosValues);

        // Open arms gesture forward
        const rArmQ = [];
        const lArmQ = [];
        [-0.2, -0.45, -0.35, -0.5, -0.2].forEach(pitch => {
            const qR = new THREE.Quaternion().setFromEuler(new THREE.Euler(pitch, 0.25, -0.2));
            const qL = new THREE.Quaternion().setFromEuler(new THREE.Euler(pitch, -0.25, 0.2));
            rArmQ.push(qR.x, qR.y, qR.z, qR.w);
            lArmQ.push(qL.x, qL.y, qL.z, qL.w);
        });

        const rArmTrack = new THREE.QuaternionKeyframeTrack('RightArm.quaternion', times, rArmQ);
        const lArmTrack = new THREE.QuaternionKeyframeTrack('LeftArm.quaternion', times, lArmQ);

        return new THREE.AnimationClip('explain', duration, [chestTrack, rArmTrack, lArmTrack]);
    }

    /**
     * 4. Point Laser Clicker Clip (Duration: 1.5s)
     */
    createPointClip() {
        const duration = 1.5;
        const times = [0, 0.3, 0.7, 1.1, 1.5];

        // Right arm raised precisely pointing forward
        const rArmQ = [];
        times.forEach((t, idx) => {
            const elev = (idx === 0) ? -0.1 : -0.75 - Math.sin(t * 3) * 0.05;
            const q = new THREE.Quaternion().setFromEuler(new THREE.Euler(elev, 0.15, -0.05));
            rArmQ.push(q.x, q.y, q.z, q.w);
        });
        const rArmTrack = new THREE.QuaternionKeyframeTrack('RightArm.quaternion', times, rArmQ);

        // Left arm stabilizing tablet
        const lArmQ = [];
        times.forEach(() => {
            const q = new THREE.Quaternion().setFromEuler(new THREE.Euler(-0.3, -0.2, 0.25));
            lArmQ.push(q.x, q.y, q.z, q.w);
        });
        const lArmTrack = new THREE.QuaternionKeyframeTrack('LeftArm.quaternion', times, lArmQ);

        return new THREE.AnimationClip('point', duration, [rArmTrack, lArmTrack]);
    }

    /**
     * 5. Shocked / Crisis Reaction Clip (Duration: 1.0s)
     */
    createShockedClip() {
        const duration = 1.0;
        const times = [0, 0.15, 0.35, 0.7, 1.0];

        // Sudden head and chest pullback
        const chestPosValues = [
            0, 0.35, 0,
            0, 0.34, -0.06, // flinch back
            0, 0.35, -0.04,
            0, 0.35, -0.02,
            0, 0.35, 0
        ];
        const chestTrack = new THREE.VectorKeyframeTrack('Chest.position', times, chestPosValues);

        // Both arms raised defensively
        const rArmQ = [];
        const lArmQ = [];
        times.forEach((t, i) => {
            const angle = (i === 1) ? -0.65 : (i === 2) ? -0.55 : -0.15;
            const qR = new THREE.Quaternion().setFromEuler(new THREE.Euler(angle, 0.2, -0.3));
            const qL = new THREE.Quaternion().setFromEuler(new THREE.Euler(angle, -0.2, 0.3));
            rArmQ.push(qR.x, qR.y, qR.z, qR.w);
            lArmQ.push(qL.x, qL.y, qL.z, qL.w);
        });
        const rArmTrack = new THREE.QuaternionKeyframeTrack('RightArm.quaternion', times, rArmQ);
        const lArmTrack = new THREE.QuaternionKeyframeTrack('LeftArm.quaternion', times, lArmQ);

        return new THREE.AnimationClip('shocked', duration, [chestTrack, rArmTrack, lArmTrack]);
    }

    /**
     * 6. Celebrate / Victory Clip (Duration: 1.8s)
     */
    createCelebrateClip() {
        const duration = 1.8;
        const times = [0, 0.4, 0.8, 1.2, 1.8];

        // Upright confident chest
        const chestPosValues = [0, 0.35, 0, 0, 0.39, 0, 0, 0.40, 0, 0, 0.38, 0, 0, 0.35, 0];
        const chestTrack = new THREE.VectorKeyframeTrack('Chest.position', times, chestPosValues);

        // Fist pump on right arm
        const rArmQ = [];
        times.forEach((t, i) => {
            const angle = (i === 1 || i === 2) ? -1.1 : -0.2;
            const q = new THREE.Quaternion().setFromEuler(new THREE.Euler(angle, 0.1, -0.2));
            rArmQ.push(q.x, q.y, q.z, q.w);
        });
        const rArmTrack = new THREE.QuaternionKeyframeTrack('RightArm.quaternion', times, rArmQ);

        return new THREE.AnimationClip('celebrate', duration, [chestTrack, rArmTrack]);
    }

    /**
     * Smooth Cross-Fading between animation states (Decision D-150)
     */
    play(actionName, duration = 0.35) {
        if (!this.actions[actionName]) return;
        const nextAction = this.actions[actionName];
        const prevAction = this.actions[this.currentActionName];

        if (prevAction && prevAction !== nextAction) {
            nextAction.reset();
            nextAction.time = 0.0;
            nextAction.enabled = true;
            nextAction.setEffectiveTimeScale(1.0);
            nextAction.setEffectiveWeight(1.0);
            prevAction.crossFadeTo(nextAction, duration, true);
        } else {
            nextAction.reset();
            nextAction.play();
        }

        this.currentActionName = actionName;
    }

    /**
     * Cinematic 3D Camera Sequencer (Dolly, Orbit & Vertigo Zoom)
     */
    dollyCameraTo(targetPos, lookAtPos, durationMs = 1200) {
        if (!this.sim || !this.sim.camera) return;
        const cam = this.sim.camera;
        const startPos = cam.position.clone();
        const startTime = performance.now();

        const step = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(1.0, elapsed / durationMs);
            // Cubic ease-out
            const ease = 1 - Math.pow(1 - progress, 3);

            cam.position.lerpVectors(startPos, targetPos, ease);
            if (lookAtPos) cam.lookAt(lookAtPos);

            if (progress < 1.0) {
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    }

    /**
     * Spawns Floating 3D Holographic Data Rings around the character
     */
    spawnHologramDataRings() {
        if (!this.sim || !this.sim.scene) return;
        if (this.hologramGroup) {
            this.sim.scene.remove(this.hologramGroup);
        }

        this.hologramGroup = new THREE.Group();
        this.hologramGroup.position.set(0, 1.2, 0);

        // 3 concentric neon holographic rings
        const colors = [0x10b981, 0x06b6d4, 0xf59e0b];
        const radii = [0.65, 0.85, 1.05];

        radii.forEach((r, idx) => {
            const geom = new THREE.TorusGeometry(r, 0.012, 8, 48);
            const mat = new THREE.MeshBasicMaterial({
                color: colors[idx],
                wireframe: true,
                transparent: true,
                opacity: 0.75
            });
            const ring = new THREE.Mesh(geom, mat);
            ring.rotation.x = Math.PI / 2 + (idx * 0.2);
            ring.name = `holoRing_${idx}`;
            this.hologramGroup.add(ring);
        });

        this.sim.scene.add(this.hologramGroup);
    }

    /**
     * Updates Mixer & Motion Graphics every frame
     */
    update(delta) {
        if (this.mixer) {
            this.mixer.update(delta);
        }

        // Animate Holographic Rings if active
        if (this.hologramGroup) {
            this.hologramGroup.children.forEach((ring, idx) => {
                ring.rotation.z += (0.015 + idx * 0.01);
                ring.rotation.y += (0.008 - idx * 0.005);
            });
        }
    }
}

// Global window registration
window.Neptune3DAnimationEngine = Neptune3DAnimationEngine;
