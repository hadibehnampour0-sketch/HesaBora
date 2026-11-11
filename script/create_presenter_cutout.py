import numpy as np
from PIL import Image, ImageFilter

def create_cutout(input_path, output_path):
    img = Image.open(input_path).convert('RGB')
    rgb = np.array(img, dtype=np.float32)
    h, w, _ = rgb.shape
    lum = 0.299 * rgb[:, :, 0] + 0.587 * rgb[:, :, 1] + 0.114 * rgb[:, :, 2]
    
    # Base solid mask
    mask = np.ones((h, w), dtype=np.float32) * 255.0

    # Top area above hair (first 65 rows) is 100% background
    mask[:65, :] = 0.0

    for y in range(65, h):
        # Scan from left to find body edge
        left_x = 0
        while left_x < w // 2:
            val = lum[y, left_x]
            r = rgb[y, left_x, 0]
            # Rim light or character silhouette edge
            if val > 42 or r > 28:
                break
            left_x += 1
        mask[y, :left_x] = 0.0

        # Scan from right to find body edge
        right_x = w - 1
        while right_x > w // 2:
            val = lum[y, right_x]
            r = rgb[y, right_x, 0]
            if val > 42 or r > 28:
                break
            right_x -= 1
        mask[y, right_x+1:] = 0.0

    # Convert to PIL Image for pristine Gaussian feathering on the edges
    mask_img = Image.fromarray(mask.astype(np.uint8), mode='L')
    mask_blurred = mask_img.filter(ImageFilter.GaussianBlur(radius=2.0))

    # Apply alpha channel
    rgba = img.convert('RGBA')
    rgba.putalpha(mask_blurred)

    # Crop excess transparent borders to make it a tight bounding box
    bbox = rgba.getbbox()
    if bbox:
        # Add 10px margin
        cropped = rgba.crop((max(0, bbox[0]-10), max(0, bbox[1]-10), min(w, bbox[2]+10), min(h, bbox[3]+10)))
    else:
        cropped = rgba

    cropped.save(output_path, 'PNG', optimize=True)
    print(f"Successfully generated clean presenter cutout: {output_path} (Final size: {cropped.size})")

if __name__ == '__main__':
    create_cutout('public/images/arash_keynote_presenter.jpg', 'public/images/arash_presenter_cutout.png')
