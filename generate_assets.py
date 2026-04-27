# -*- coding: utf-8 -*-
from PIL import Image, ImageDraw
import os

src_dir     = r'c:/xampp/htdocs/projeto-ponto-web/ponto_app'
android_res = r'c:/xampp/htdocs/projeto-ponto-web/ponto_app/android/app/src/main/res'
ios_icon    = r'c:/xampp/htdocs/projeto-ponto-web/ponto_app/ios/Runner/Assets.xcassets/AppIcon.appiconset'
ios_launch  = r'c:/xampp/htdocs/projeto-ponto-web/ponto_app/ios/Runner/Assets.xcassets/LaunchImage.imageset'
fl_assets   = r'c:/xampp/htdocs/projeto-ponto-web/ponto_app/assets/images'
os.makedirs(fl_assets, exist_ok=True)

logo  = Image.open(os.path.join(src_dir, 'RM-PONTO-fundo-branco.png')).convert('RGBA')
notif = Image.open(os.path.join(src_dir, 'RM-PONTO-notificacao.png')).convert('RGBA')

def resize_sq(img, px):
    return img.resize((px, px), Image.LANCZOS)

def save_rgb(img, path):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    final = Image.new('RGB', img.size, (255, 255, 255))
    if img.mode == 'RGBA':
        final.paste(img, mask=img.split()[3])
    else:
        final.paste(img)
    final.save(path, 'PNG', optimize=True)
    print('OK ' + path)

def save_rgba(img, path):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    img.save(path, 'PNG', optimize=True)
    print('OK ' + path)

# ─── 1. Android ic_launcher ───────────────────────────────────────────────────
print('=== Android ic_launcher ===')
android_sizes = {
    'mipmap-mdpi':    48,
    'mipmap-hdpi':    72,
    'mipmap-xhdpi':   96,
    'mipmap-xxhdpi':  144,
    'mipmap-xxxhdpi': 192,
}
for folder, px in android_sizes.items():
    save_rgb(resize_sq(logo, px), os.path.join(android_res, folder, 'ic_launcher.png'))

    # Versao redonda com fundo branco circular
    circ = Image.new('RGBA', (px, px), (0, 0, 0, 0))
    bg   = Image.new('RGBA', (px, px), (255, 255, 255, 255))
    mask = Image.new('L', (px, px), 0)
    draw = ImageDraw.Draw(mask)
    draw.ellipse([0, 0, px, px], fill=255)
    inner = resize_sq(logo, px)
    circ.paste(bg, mask=mask)
    circ.paste(inner, mask=inner.split()[3])
    # Clip to circle
    result = Image.new('RGBA', (px, px), (0, 0, 0, 0))
    result.paste(circ, mask=mask)
    save_rgba(result, os.path.join(android_res, folder, 'ic_launcher_round.png'))

# ─── 2. Android notification icon (branco puro + transparente) ────────────────
print('=== Android notification icon ===')
notif_sizes = {
    'drawable-mdpi':    24,
    'drawable-hdpi':    36,
    'drawable-xhdpi':   48,
    'drawable-xxhdpi':  72,
    'drawable-xxxhdpi': 96,
}
for folder, px in notif_sizes.items():
    out_dir = os.path.join(android_res, folder)
    os.makedirs(out_dir, exist_ok=True)
    base = notif.resize((px, px), Image.LANCZOS).convert('RGBA')
    r, g, b, a = base.split()
    white = Image.new('L', base.size, 255)
    result = Image.merge('RGBA', [white, white, white, a])
    save_rgba(result, os.path.join(out_dir, 'ic_notification.png'))

# ─── 3. iOS AppIcon ───────────────────────────────────────────────────────────
print('=== iOS AppIcon ===')
ios_icon_specs = [
    ('Icon-App-20x20@1x.png',     20),
    ('Icon-App-20x20@2x.png',     40),
    ('Icon-App-20x20@3x.png',     60),
    ('Icon-App-29x29@1x.png',     29),
    ('Icon-App-29x29@2x.png',     58),
    ('Icon-App-29x29@3x.png',     87),
    ('Icon-App-40x40@1x.png',     40),
    ('Icon-App-40x40@2x.png',     80),
    ('Icon-App-40x40@3x.png',    120),
    ('Icon-App-60x60@2x.png',    120),
    ('Icon-App-60x60@3x.png',    180),
    ('Icon-App-76x76@1x.png',     76),
    ('Icon-App-76x76@2x.png',    152),
    ('Icon-App-83.5x83.5@2x.png',167),
    ('Icon-App-1024x1024@1x.png',1024),
]
for fname, px in ios_icon_specs:
    save_rgb(resize_sq(logo, px), os.path.join(ios_icon, fname))

# ─── 4. iOS LaunchImage ───────────────────────────────────────────────────────
print('=== iOS LaunchImage ===')
def make_launch(logo_img, w, h):
    bg = Image.new('RGB', (w, h), (255, 255, 255))
    logo_sz = min(w, h) // 2
    resized = logo_img.resize((logo_sz, logo_sz), Image.LANCZOS)
    x = (w - logo_sz) // 2
    y = (h - logo_sz) // 2
    if resized.mode == 'RGBA':
        bg.paste(resized, (x, y), resized.split()[3])
    else:
        bg.paste(resized, (x, y))
    return bg

for fname, w, h in [
    ('LaunchImage.png',    320,  480),
    ('LaunchImage@2x.png', 640,  960),
    ('LaunchImage@3x.png', 750, 1334),
]:
    img = make_launch(logo, w, h)
    out_path = os.path.join(ios_launch, fname)
    img.save(out_path, 'PNG', optimize=True)
    print('OK ' + out_path)

# ─── 5. Flutter assets (logo_login + ic_notification) ────────────────────────
print('=== Flutter assets ===')
for density, factor in [('1.0x', 1.0), ('2.0x', 2.0), ('3.0x', 3.0)]:
    px = int(280 * factor)
    resized = resize_sq(logo, px)
    subdir = fl_assets if density == '1.0x' else os.path.join(fl_assets, density)
    os.makedirs(subdir, exist_ok=True)
    out = os.path.join(subdir, 'logo_login.png')
    resized.save(out, 'PNG', optimize=True)
    print('OK ' + out)

notif_app = notif.resize((96, 96), Image.LANCZOS)
save_rgba(notif_app, os.path.join(fl_assets, 'ic_notification.png'))

print('')
print('TODOS OS ASSETS GERADOS COM SUCESSO!')
