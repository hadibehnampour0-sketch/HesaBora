import numpy as np
from PIL import Image, ImageFilter

def generate_human_depth_map(cutout_path, output_depth_path, output_normal_path=None):
    img = Image.open(cutout_path)
    rgba = np.array(img, dtype=np.float32)
    h, w, _ = rgba.shape
    alpha = rgba[:, :, 3] / 255.0
    
    # 1. Base silhouette distance from edge (gives organic 3D volumetric extrusion)
    # Binary mask
    mask = (alpha > 0.3).astype(np.uint8)
    
    # Fast multi-scale Gaussian layering to simulate 3D volume
    vol = np.zeros((h, w), dtype=np.float32)
    base_mask_img = Image.fromarray((mask * 255).astype(np.uint8))
    
    # Layer several blur radii to create a smooth 3D rounded dome across the human body
    for r in [6, 15, 30, 60, 110]:
        blurred = np.array(base_mask_img.filter(ImageFilter.GaussianBlur(radius=r)), dtype=np.float32) / 255.0
        vol += blurred
    vol = vol / 5.0  # Normalized [0, 1]
    
    # 2. Anatomical depth cues
    # Head region (around y=190, x=300): convex dome (nose forward, cheeks receding)
    y_coords, x_coords = np.ogrid[:h, :w]
    
    head_cy, head_cx = 195, int(w * 0.50)
    head_dist = np.sqrt(((y_coords - head_cy) / 110.0)**2 + ((x_coords - head_cx) / 85.0)**2)
    head_dome = np.clip(1.0 - head_dist, 0.0, 1.0)
    # Shape nose prominence
    nose_dist = np.sqrt(((y_coords - (head_cy + 10)) / 25.0)**2 + ((x_coords - head_cx) / 18.0)**2)
    nose_bump = np.clip(1.0 - nose_dist, 0.0, 1.0) * 0.25
    
    # Chest / Torso region (y=360 to 650): rounded cylinder leaning slightly forward
    torso_cy, torso_cx = 480, int(w * 0.49)
    torso_dist = np.abs(x_coords - torso_cx) / 160.0
    torso_dome = np.clip(1.0 - torso_dist, 0.0, 1.0)
    torso_vertical_weight = np.clip(1.0 - (y_coords - 330) / 750.0, 0.2, 1.0)
    
    # Hand holding laser presenter remote (around y=420, x=440): projecting forward toward camera
    hand_cy, hand_cx = 420, int(w * 0.74)
    hand_dist = np.sqrt(((y_coords - hand_cy) / 50.0)**2 + ((x_coords - hand_cx) / 45.0)**2)
    hand_forward = np.clip(1.0 - hand_dist, 0.0, 1.0) * 0.35
    
    # Combine volumetric layers
    depth = (vol * 0.45) + (head_dome * 0.35) + (nose_bump) + (torso_dome * torso_vertical_weight * 0.25) + hand_forward
    
    # Modulate slightly with luminance details (fabric folds, lapel, collar) for micro-geometry
    lum = (0.299 * rgba[:, :, 0] + 0.587 * rgba[:, :, 1] + 0.114 * rgba[:, :, 2]) / 255.0
    depth += (lum - 0.5) * 0.08
    
    # Apply alpha boundary mask
    depth = np.clip(depth, 0.0, 1.0) * alpha
    
    # Convert to 8-bit greyscale depth map
    depth_8bit = (depth * 255.0).astype(np.uint8)
    depth_img = Image.fromarray(depth_8bit, mode='L')
    depth_img = depth_img.filter(ImageFilter.GaussianBlur(radius=1.5))
    depth_img.save(output_depth_path, 'PNG')
    print(f"Saved 3D depth map to {output_depth_path}")

    # Generate 3D Normal Map from depth gradients for realistic PBR lighting in WebGL
    if output_normal_path:
        depth_f = np.array(depth_img, dtype=np.float32) / 255.0
        # Sobel-like gradients
        dzdx = (np.roll(depth_f, -1, axis=1) - np.roll(depth_f, 1, axis=1)) * 3.5
        dzdy = (np.roll(depth_f, -1, axis=0) - np.roll(depth_f, 1, axis=0)) * 3.5
        dzdz = np.ones_like(depth_f)
        
        # Normalize normal vector
        norm = np.sqrt(dzdx**2 + dzdy**2 + dzdz**2)
        nx = (dzdx / norm) * 0.5 + 0.5
        ny = (dzdy / norm) * 0.5 + 0.5
        nz = (dzdz / norm) * 0.5 + 0.5
        
        # Normal map RGB: R=X, G=Y, B=Z
        normal_rgb = np.stack([nx, ny, nz], axis=-1)
        normal_8bit = (normal_rgb * 255.0).astype(np.uint8)
        # Mask with alpha
        normal_rgba = np.dstack([normal_8bit, (alpha * 255.0).astype(np.uint8)])
        normal_img = Image.fromarray(normal_rgba, mode='RGBA')
        normal_img.save(output_normal_path, 'PNG')
        print(f"Saved 3D normal map to {output_normal_path}")

if __name__ == '__main__':
    generate_human_depth_map(
        'public/images/arash_presenter_cutout.png',
        'public/images/arash_depth_map.png',
        'public/images/arash_normal_map.png'
    )
