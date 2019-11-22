/*
 * Copyright (c) Enalean, 2019-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */
import { ActionContext } from "vuex";
import * as actions from "./drag-drop-actions";
import * as item_finder from "../../helpers/html-to-item";
import * as card_positioner from "../../helpers/cards-reordering";
import { HandleDropPayload, SwimlaneState } from "./type";
import { RootState } from "../type";
import { Card, ColumnDefinition, Swimlane, Direction } from "../../type";

function createElement(): HTMLElement {
    const local_document = document.implementation.createHTMLDocument();
    return local_document.createElement("div");
}

function createPayload(): HandleDropPayload {
    const dropped_card = createElement();
    const target_cell = createElement();
    const source_cell = createElement();
    const sibling_card = createElement();
    return { dropped_card, target_cell, source_cell, sibling_card };
}

describe(`drag-drop-actions`, () => {
    let context: ActionContext<SwimlaneState, RootState>;

    beforeEach(() => {
        context = ({
            commit: jest.fn(),
            getters: {
                column_and_swimlane_of_cell: jest.fn(),
                cards_in_cell: jest.fn()
            },
            dispatch: jest.fn(),
            rootState: {} as RootState
        } as unknown) as ActionContext<SwimlaneState, RootState>;
    });

    describe(`handleDrop()`, () => {
        it(`When the dropped card has been dropped in another swimlane,
            it will do nothing`, async () => {
            const hasBeenDropped = jest
                .spyOn(item_finder, "hasCardBeenDroppedInAnotherSwimlane")
                .mockReturnValue(true);

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(hasBeenDropped).toHaveBeenCalledWith(payload.target_cell, payload.source_cell);
            expect(context.dispatch).not.toHaveBeenCalled();
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`When the swimlane of the target cell can't be found,
            it will do nothing`, async () => {
            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(true);

            context.getters.column_and_swimlane_of_cell.mockImplementation(() => {
                return { swimlane: undefined, column: { id: 31 } as ColumnDefinition };
            });
            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(context.getters.column_and_swimlane_of_cell).toHaveBeenCalledWith(
                payload.target_cell
            );
            expect(context.dispatch).not.toHaveBeenCalled();
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`When the column of the target cell can't be found,
                it will do nothing`, async () => {
            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(true);

            context.getters.column_and_swimlane_of_cell.mockImplementation(() => {
                return { swimlane: { card: { id: 543 } } as Swimlane, column: undefined };
            });
            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(context.getters.column_and_swimlane_of_cell).toHaveBeenCalledWith(
                payload.target_cell
            );
            expect(context.dispatch).not.toHaveBeenCalled();
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`When the dropped card can't be found in the state,
            it will do nothing`, async () => {
            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(true);
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const swimlane = { card: { id: 543, has_children: true } } as Swimlane;
            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });

            const getCard = jest.spyOn(item_finder, "getCardFromSwimlane").mockReturnValue(null);

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(getCard).toHaveBeenCalledWith(swimlane, payload.dropped_card);
            expect(context.dispatch).not.toHaveBeenCalled();
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`When there is no sibling card,
            it will reorder cards in the cell`, async () => {
            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(true);
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const swimlane = { card: { id: 543 } } as Swimlane;
            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });
            const card = { id: 667, label: "Do the stuff" } as Card;
            jest.spyOn(item_finder, "getCardFromSwimlane")
                .mockReturnValueOnce(card)
                .mockReturnValueOnce(null);
            const before_sibling = { id: 778, label: "Documentation" };
            context.getters.cards_in_cell.mockReturnValue([before_sibling, card]);

            const position = {
                ids: [667],
                direction: Direction.AFTER,
                compared_to: 778
            };
            jest.spyOn(card_positioner, "getCardPosition").mockReturnValue(position);

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(context.dispatch).toHaveBeenCalledWith("reorderCardsInCell", {
                swimlane,
                column,
                position
            });
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`Given a dropped card and a target cell,
            it will reorder cards in the cell`, async () => {
            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(true);
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const swimlane = { card: { id: 543 } } as Swimlane;
            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });
            const card = { id: 667, label: "Do the stuff" } as Card;
            const sibling = { id: 778, label: "Documentation" } as Card;
            jest.spyOn(item_finder, "getCardFromSwimlane")
                .mockReturnValueOnce(card)
                .mockReturnValueOnce(sibling);
            context.getters.cards_in_cell.mockReturnValue([sibling, card]);

            const position = {
                ids: [667],
                direction: Direction.BEFORE,
                compared_to: 778
            };
            jest.spyOn(card_positioner, "getCardPosition").mockReturnValue(position);

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(context.dispatch).toHaveBeenCalledWith("reorderCardsInCell", {
                swimlane,
                column,
                position
            });
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`Given a dropped card, a target cell but no children inside,
            it will only change the card of column`, async () => {
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const swimlane = { card: { id: 543 } } as Swimlane;

            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });

            const card = { id: 667, label: "Do the stuff" } as Card;
            const sibling = null;

            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(false);
            jest.spyOn(item_finder, "getCardFromSwimlane")
                .mockReturnValueOnce(card)
                .mockReturnValueOnce(sibling);

            context.getters.cards_in_cell.mockReturnValue([]);

            jest.spyOn(card_positioner, "getCardPosition");

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(card_positioner.getCardPosition).not.toHaveBeenCalled();
            expect(context.dispatch).toHaveBeenCalledWith("moveCardToCell", {
                card,
                swimlane,
                column
            });
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`Given a dropped card, a target cell with some children inside,
            it will change the card of column and put it at the given position`, async () => {
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const swimlane = { card: { id: 543 } } as Swimlane;

            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });

            const card = { id: 667, label: "Do the stuff" } as Card;
            const sibling = { id: 778, label: "Documentation" } as Card;

            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(false);
            jest.spyOn(item_finder, "getCardFromSwimlane")
                .mockReturnValueOnce(card)
                .mockReturnValueOnce(sibling);

            context.getters.cards_in_cell.mockReturnValue([sibling]);

            const position = {
                ids: [667],
                direction: Direction.BEFORE,
                compared_to: 778
            };

            jest.spyOn(card_positioner, "getCardPosition");

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(card_positioner.getCardPosition).toHaveBeenCalledWith(card, sibling, [sibling]);
            expect(context.dispatch).toHaveBeenCalledWith("moveCardToCell", {
                card,
                swimlane,
                column,
                position
            });
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`Given a dropped card in a solo card swimlane,
            it will change the card of column`, async () => {
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const card = { id: 667, label: "Do the stuff" } as Card;
            const swimlane = { card, children_cards: [] as Card[] } as Swimlane;

            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });

            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(false);
            jest.spyOn(item_finder, "getCardFromSwimlane")
                .mockReturnValueOnce(card)
                .mockReturnValueOnce(null);

            context.getters.cards_in_cell.mockReturnValue([]);

            jest.spyOn(card_positioner, "getCardPosition");

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(card_positioner.getCardPosition).not.toHaveBeenCalled();
            expect(context.dispatch).toHaveBeenCalledWith("moveCardToCell", {
                card,
                swimlane,
                column
            });
            expect(context.commit).toHaveBeenCalledWith("removeHighlightOnLastHoveredDropZone");
        });

        it(`
            Given a solo card
            When it has been dragged over some different containers and dropped in the source one
            Then it should not try to reorder the cards
        `, async () => {
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const card = { id: 667, label: "Do the stuff", has_children: false } as Card;
            const swimlane = { card } as Swimlane;

            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });

            const sibling = null;

            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(true);
            jest.spyOn(item_finder, "getCardFromSwimlane")
                .mockReturnValueOnce(card)
                .mockReturnValueOnce(sibling);

            context.getters.cards_in_cell.mockReturnValue([card]); // Card is alone in its cell

            jest.spyOn(card_positioner, "getCardPosition");

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(card_positioner.getCardPosition).not.toHaveBeenCalled();
            expect(context.dispatch).not.toHaveBeenCalledWith();
        });

        it(`
            Given a child card alone in a cell
            When it has been dragged over some different containers and dropped in the source one
            Then it should not try to reorder the cards
        `, async () => {
            const column = { id: 31, label: "Todo" } as ColumnDefinition;
            const swimlane = { card: { id: 666, has_children: true } } as Swimlane;
            const card = { id: 667, label: "Do the stuff", has_children: false } as Card;

            context.getters.column_and_swimlane_of_cell.mockReturnValue({ column, swimlane });

            const sibling = null;

            jest.spyOn(item_finder, "hasCardBeenDroppedInTheSameCell").mockReturnValue(true);
            jest.spyOn(item_finder, "getCardFromSwimlane")
                .mockReturnValueOnce(card)
                .mockReturnValueOnce(sibling);

            context.getters.cards_in_cell.mockReturnValue([card]); // Card is alone in its cell

            jest.spyOn(card_positioner, "getCardPosition");

            const payload = createPayload();
            await actions.handleDrop(context, payload);

            expect(card_positioner.getCardPosition).not.toHaveBeenCalled();
            expect(context.dispatch).not.toHaveBeenCalledWith();
        });
    });
});
