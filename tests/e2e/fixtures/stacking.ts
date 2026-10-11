import { expect, type Locator } from '@playwright/test';

interface Box {
    x: number;
    y: number;
    width: number;
    height: number;
}

interface OverlapStrip {
    x: number;
    first: number;
    last: number;
}

/**
 * Asserts that `top` paints above `below` across their whole geometric
 * overlap — the paint-order check that `z-index` numbers alone cannot
 * express (stacking contexts, later-in-DOM siblings). Hit-tests
 * `document.elementFromPoint` down the overlap's center line; if any other
 * element wins inside `top`'s box, the assertion fails.
 *
 * Throws when either element is absent or the boxes do not overlap, so a
 * misplaced element can never pass vacuously.
 */
export async function expectStackedAbove(top: Locator, below: Locator): Promise<void> {
    const topBox = await requireBoundingBox(top);
    const belowBox = await requireBoundingBox(below);
    const strip = overlapStrip(topBox, belowBox);
    const onTop = await hitTestPoints(top, strip.x, samplePoints(strip));

    expect(
        onTop,
        'the first element must be the topmost across its overlap with the second',
    ).not.toContain(false);
}

async function requireBoundingBox(locator: Locator): Promise<Box> {
    const box = await locator.boundingBox();

    if (box === null) {
        throw new Error(`expectStackedAbove: element is not visible: ${locator}`);
    }

    return box;
}

function overlapStrip(topBox: Box, belowBox: Box): OverlapStrip {
    const insetPx = 2;
    const top = Math.max(topBox.y, belowBox.y);
    const bottom = Math.min(topBox.y + topBox.height, belowBox.y + belowBox.height);

    if (bottom <= top) {
        throw new Error('expectStackedAbove: the elements do not overlap');
    }

    return {
        x: topBox.x + topBox.width / 2,
        first: top + insetPx,
        last: Math.max(top + insetPx, bottom - insetPx),
    };
}

function samplePoints({ first, last }: OverlapStrip): number[] {
    const sampleCount = 9;

    return Array.from(
        { length: sampleCount },
        (_, index) => first + ((last - first) * index) / (sampleCount - 1),
    );
}

async function hitTestPoints(locator: Locator, x: number, points: number[]): Promise<boolean[]> {
    return locator.evaluate(
        (element, args: { x: number; points: number[] }) =>
            args.points.map((y) => {
                const hit = document.elementFromPoint(args.x, y);

                return hit !== null && element.contains(hit);
            }),
        { x, points },
    );
}
