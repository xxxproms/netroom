import { Position } from '@vue-flow/core';
import type { GraphNode } from '@vue-flow/core';

/**
 * Geometry for "floating" edges — ones that meet a node on whichever side faces
 * the other end, instead of at a fixed handle. Lets the topology stay legible
 * however the nodes are dragged around. Adapted from Vue Flow's own example.
 */

type Point = { x: number; y: number };

function center(node: GraphNode): Point {
    return {
        x: node.computedPosition.x + (node.dimensions.width || 0) / 2,
        y: node.computedPosition.y + (node.dimensions.height || 0) / 2,
    };
}

/** Where the line between two node centres crosses the first node's border. */
function intersection(node: GraphNode, other: GraphNode): Point {
    const w = (node.dimensions.width || 0) / 2;
    const h = (node.dimensions.height || 0) / 2;

    const c = center(node);
    const o = center(other);

    if (w === 0 || h === 0) {
        return c;
    }

    const xx = (o.x - c.x) / (2 * w) - (o.y - c.y) / (2 * h);
    const yy = (o.x - c.x) / (2 * w) + (o.y - c.y) / (2 * h);
    const a = 1 / (Math.abs(xx) + Math.abs(yy) || 1);
    const bx = a * xx;
    const by = a * yy;

    return { x: w * (bx + by) + c.x, y: h * (-bx + by) + c.y };
}

/** Which side of a node the intersection point sits on. */
function side(node: GraphNode, point: Point): Position {
    const x = Math.round(node.computedPosition.x);
    const y = Math.round(node.computedPosition.y);
    const width = node.dimensions.width || 0;
    const px = Math.round(point.x);
    const py = Math.round(point.y);

    if (px <= x + 1) {
        return Position.Left;
    }

    if (px >= x + width - 1) {
        return Position.Right;
    }

    if (py <= y + 1) {
        return Position.Top;
    }

    return Position.Bottom;
}

export type EdgeGeometry = {
    sx: number;
    sy: number;
    tx: number;
    ty: number;
    sourcePos: Position;
    targetPos: Position;
};

export function getEdgeParams(
    source: GraphNode,
    target: GraphNode,
): EdgeGeometry {
    const sp = intersection(source, target);
    const tp = intersection(target, source);

    return {
        sx: sp.x,
        sy: sp.y,
        tx: tp.x,
        ty: tp.y,
        sourcePos: side(source, sp),
        targetPos: side(target, tp),
    };
}
