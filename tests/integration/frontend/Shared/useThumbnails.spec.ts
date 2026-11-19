import { toThumbnailView } from '@shared-cross/Composables/useThumbnails';
import { describe, expect, it } from 'vitest';

describe('toThumbnailView', () => {
    it('resolves the pair into /assets URLs and a 1x/2x srcset', () => {
        expect(
            toThumbnailView({
                path: 'projects/shot.thumb.webp',
                retina_path: 'projects/shot.thumb@2x.webp',
            }),
        ).toEqual({
            url: '/assets/projects/shot.thumb.webp',
            srcset:
                '/assets/projects/shot.thumb.webp 1x, /assets/projects/shot.thumb@2x.webp 2x',
        });
    });

    it('returns null until both variants exist', () => {
        expect(toThumbnailView(null)).toBeNull();
        expect(toThumbnailView(undefined)).toBeNull();
        expect(toThumbnailView({ path: 'projects/shot.thumb.webp' } as never)).toBeNull();
    });
});
