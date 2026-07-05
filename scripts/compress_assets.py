import os
from PIL import Image

def optimize_images():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    
    # 1. Optimize logo.png in-place (since it is referenced in manifests/PWA specs)
    logo_path = os.path.join(base_dir, 'logo.png')
    if os.path.exists(logo_path):
        print(f"Optimizing {logo_path}...")
        img = Image.open(logo_path)
        img.save(logo_path, 'PNG', optimize=True)
        print(f"logo.png optimized. Size: {os.path.getsize(logo_path)} bytes")

    # 2. Convert PNGs in /imgs/ to WebP
    imgs_dir = os.path.join(base_dir, 'imgs')
    images_to_convert = [
        ('Card-Back.png', 'Card-Back.webp'),
        ('Sunday School App.png', 'Sunday-School-App.webp'),
        ('Sunday-School-Og.png', 'Sunday-School-Og.webp'),
    ]
    
    for src_name, dest_name in images_to_convert:
        src_path = os.path.join(imgs_dir, src_name)
        dest_path = os.path.join(imgs_dir, dest_name)
        if os.path.exists(src_path):
            print(f"Converting {src_path} to WebP...")
            img = Image.open(src_path)
            img.save(dest_path, 'WEBP', quality=85)
            print(f"Saved {dest_path}. Size: {os.path.getsize(dest_path)} bytes")
        else:
            print(f"Warning: {src_path} does not exist.")

if __name__ == '__main__':
    optimize_images()
