// Image assets preparation pipeline (Makefile `images` target) — runs
// inside the dev container (node 24): optimizes/minifies every asset in
// `resources/images/` into `public/images/` (SVGs via SVGO, rasters via
// sharp re-encode) and generates the section favicons from
// `logo-favicon.svg`: `favicon-{public,admin}.svg` (optimized copy) +
// `favicon-{public,admin}-{32,180}.png` (rasterized). Deterministic and
// idempotent — files are always rewritten; assets living only in
// `public/images/` (e.g. `og-default.png`) are never touched.
import { mkdir, readFile, readdir, writeFile, copyFile } from 'node:fs/promises';
import { extname, join } from 'node:path';
import sharp from 'sharp';
import { optimize } from 'svgo';

const SRC_DIR = 'resources/images';
const DEST_DIR = 'public/images';
const FAVICON_SOURCE = 'logo-favicon.svg';
const FAVICON_SECTIONS = ['public', 'admin'];
const FAVICON_PNG_SIZES = [32, 180];
const RASTER_EXTS = new Set(['.png', '.jpg', '.jpeg', '.webp', '.avif']);

async function readText(path) {
    return readFile(path, 'utf8');
}

async function optimizeSvg(sourcePath, targetPath, svgoPath) {
    const raw = await readText(sourcePath);
    const { data } = optimize(raw, { path: svgoPath });
    await writeFile(targetPath, data);
}

async function optimizeRaster(sourcePath, targetPath) {
    const ext = extname(sourcePath).toLowerCase();
    const pipeline = sharp(sourcePath).rotate();

    if (ext === '.png') {
        await pipeline.png({ compressionLevel: 9, palette: false }).toFile(targetPath);
    } else if (ext === '.webp') {
        await pipeline.webp({ quality: 85 }).toFile(targetPath);
    } else if (ext === '.avif') {
        await pipeline.avif({ quality: 80 }).toFile(targetPath);
    } else {
        await pipeline.jpeg({ quality: 85, progressive: true }).toFile(targetPath);
    }
}

async function main() {
    await mkdir(DEST_DIR, { recursive: true });

    const sources = (await readdir(SRC_DIR)).filter((file) => !file.startsWith('.'));

    for (const file of sources) {
        const sourcePath = join(SRC_DIR, file);
        const targetPath = join(DEST_DIR, file);
        const ext = extname(file).toLowerCase();

        if (ext === '.svg') {
            await optimizeSvg(sourcePath, targetPath, file);
        } else if (RASTER_EXTS.has(ext)) {
            await optimizeRaster(sourcePath, targetPath);
        } else {
            await copyFile(sourcePath, targetPath);
        }
    }

    const faviconSvg = await readText(join(SRC_DIR, FAVICON_SOURCE));
    for (const section of FAVICON_SECTIONS) {
        const { data } = optimize(faviconSvg, { path: `favicon-${section}.svg` });
        await writeFile(join(DEST_DIR, `favicon-${section}.svg`), data);

        for (const size of FAVICON_PNG_SIZES) {
            await sharp(Buffer.from(faviconSvg))
                .resize(size, size)
                .png()
                .toFile(join(DEST_DIR, `favicon-${section}-${size}.png`));
        }
    }

    console.log(
        `Prepared ${sources.length} assets + ${FAVICON_SECTIONS.length} favicon sets ` +
            `(${FAVICON_SECTIONS.join(', ')}, sizes ${FAVICON_PNG_SIZES.join('/')}px) in ${DEST_DIR}/`,
    );
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
