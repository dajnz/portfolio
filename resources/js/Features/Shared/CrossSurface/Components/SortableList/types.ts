export type SortableItem = Record<string, unknown>;

export interface SortableListClasses {
    container?: string;
    item?: string;
    placeholder?: string;
    handle?: string;
}

export interface CrossDragInfo {
    /** The dragged item (shared reference with the source list). */
    item: SortableItem;
    /** `list_id` of the list the drag started in. */
    from_list_id: string;
}
