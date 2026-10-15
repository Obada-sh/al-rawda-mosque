/**
 * Builds the default slide templates of the "leaders of each circle" export:
 * leaders-1.pptx ... leaders-10.pptx, one per number of leaders shown.
 *
 * This is a design-time tool. The application never runs it: it only reads
 * the .pptx files it produced, which are kept next to it. Run it again only
 * to change the look of the default slides:
 *
 *     npm install pptxgenjs
 *     node resources/pptx/build-default-templates.js
 *
 * A template is one slide with placeholders, which the application repeats
 * for every circle:
 *
 *   {{circle}} {{supervisor}} {{supervisor_line}} {{course}} {{period}} {{criterion}} {{mosque}}
 *
 * The mosque's logo (public/img/logo.png) is drawn into every template:
 * run the script again after changing the logo.
 *   {{place1}} {{name1}} {{score1}}   ...   {{placeN}} {{nameN}} {{scoreN}}
 *
 * Every shape of leader N is named "slotN ...": when a circle has fewer
 * leaders than the template has slots, the application removes the shapes
 * of the missing ones, so no empty card is left on the slide.
 *
 * Tied students share a place, so the second row may well hold a first
 * place. The look of a place therefore hangs on the place, not on the row.
 * Shapes of a row that differ only by "@place" in their name are the looks
 * the same thing can take: "slotN@1 medal" is shown when row N holds a
 * first place, "slotN@2 medal" a second one, and "slotN@0 medal" any place
 * that has no medal of its own. They lie on top of each other, the usual
 * one uppermost, so the slide looks right when it is opened in PowerPoint
 * to be redesigned.
 */
const path = require('path');
const PptxGenJS = require('pptxgenjs');

const MAX_SLOTS = 10;
// The mosque's logo, the same file the panel shows.
const LOGO = path.join(__dirname, '..', '..', 'public', 'img', 'logo.png');
const SLIDE = { w: 13.333, h: 7.5 };
const FONT = 'Segoe UI';

const COLOR = {
    background: '0F3D2E', // deep green
    card: '17513E',
    cardFirst: '1E664E',
    title: 'FFFFFF',
    text: 'FFFFFF',
    muted: 'A9CDBC',
    gold: 'E6B422',
    silver: 'C9D1D3',
    bronze: 'CD8A4B',
    medalText: '0F3D2E',
};

const MARGIN = 0.6;
const HEADER_H = 2.0;
const BODY_TOP = MARGIN + HEADER_H + 0.15;
const BODY_H = SLIDE.h - BODY_TOP - MARGIN;
const GAP = 0.18;
const COLUMN_GAP = 0.3;

function text(slide, value, box, options = {}) {
    slide.addText(value, {
        ...box,
        fontFace: FONT,
        color: COLOR.text,
        rtlMode: true,
        lang: 'ar-SY',
        align: 'right',
        valign: 'middle',
        margin: 0,
        isTextBox: true,
        ...options,
    });
}

const clamp = (low, high, value) => Math.max(low, Math.min(high, value));

/**
 * Where the rows go: one column up to five leaders, then two columns (the
 * right one first). One or two leaders get tall rows; a lone leader gets a
 * narrower card in the middle of the slide.
 */
function layout(slots) {
    const columns = slots > 5 ? 2 : 1;
    const perColumn = Math.ceil(slots / columns);
    const columnW = slots === 1 ? 10.5 : (SLIDE.w - 2 * MARGIN - (columns - 1) * COLUMN_GAP) / columns;
    const rowH = Math.min(slots <= 2 ? 2.4 : 1.35, (BODY_H - (perColumn - 1) * GAP) / perColumn);

    // The rows stand in the middle of the room they do not fill.
    const top = BODY_TOP + (BODY_H - perColumn * rowH - (perColumn - 1) * GAP) / 2;
    const right = SLIDE.w - (slots === 1 ? (SLIDE.w - columnW) / 2 : MARGIN);

    return {
        columns,
        rowH,
        box: (slot) => {
            const column = Math.floor((slot - 1) / perColumn);
            const row = (slot - 1) % perColumn;

            return {
                x: right - columnW - column * (columnW + COLUMN_GAP),
                y: top + row * (rowH + GAP),
                w: columnW,
                h: rowH,
            };
        },
    };
}

/**
 * The sizes of the text of a row. They follow its height, so that ten
 * leaders fit as well as three.
 *
 * In a tall row the score stands under the name. So it does in two
 * columns, where a name has little room beside its score; where the rows
 * of two columns are too low for that, both get smaller.
 */
function typography(columns, rowH) {
    if (rowH >= 1.9) {
        const name = clamp(30, 44, Math.floor(rowH * 72 * 0.25));

        return { stacked: true, name, score: Math.round(name * 0.7) };
    }

    if (columns === 1) {
        const name = clamp(18, 30, Math.floor(rowH * 72 * 0.42));

        return { stacked: false, name, score: Math.round(name * 0.8) };
    }

    if (rowH >= 0.85) {
        const name = clamp(18, 24, Math.floor(rowH * 72 * 0.31));

        return { stacked: true, name, score: Math.round(name * 0.75) };
    }

    return { stacked: false, name: 17, score: 14 };
}

/**
 * The looks the card and the medal of a row can take, the usual one last
 * (drawn uppermost). "place" is the place a look is kept for: null for
 * always, 0 for the places without a look of their own.
 *
 * The first row always holds a first place; the second holds a first or a
 * second one; from the fourth row on, the usual look is the plain one.
 */
function cardLooks(slot) {
    const first = { fill: COLOR.cardFirst, line: COLOR.gold, lineWidth: 1.5 };
    const other = { fill: COLOR.card, line: COLOR.card, lineWidth: 0 };

    return slot === 1
        ? [{ ...first, place: null }]
        : [{ ...first, place: 1 }, { ...other, place: 0 }];
}

function medalLooks(slot) {
    const byPlace = [COLOR.gold, COLOR.silver, COLOR.bronze].map((color, index) => ({ color, place: index + 1 }));

    if (slot === 1) {
        return [{ color: COLOR.gold, place: null }];
    }

    return slot <= 3 ? byPlace.slice(0, slot) : [...byPlace, { color: COLOR.muted, place: 0 }];
}

function addHeader(pres, slide) {
    // The mosque's logo (public/img/logo.png) on a white card, so that its
    // green stays visible on the green of the slide, and the mosque's name
    // beside it, at the left end of the header.
    const logo = { w: 406, h: 512 };
    const badge = { x: MARGIN, y: MARGIN - 0.15, w: 1.12, h: 1.36 };
    const logoH = badge.h - 0.2;
    const logoW = logoH * logo.w / logo.h;

    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, {
        ...badge,
        rectRadius: 0.14,
        fill: { color: 'FFFFFF' },
        line: { color: COLOR.gold, width: 1 },
        objectName: 'logo card',
    });
    slide.addImage({
        path: LOGO,
        x: badge.x + (badge.w - logoW) / 2,
        y: badge.y + (badge.h - logoH) / 2,
        w: logoW,
        h: logoH,
        altText: 'شعار الجامع',
        objectName: 'logo',
    });
    text(slide, '{{mosque}}', { x: MARGIN + 1.3, y: MARGIN - 0.15, w: 3.2, h: 1.36 }, {
        fontSize: 18, bold: true, color: COLOR.muted, align: 'left', objectName: 'mosque',
    });

    // The right half reads from the top: what is ranked, the circle, its supervisor.
    // The name of the circle has the room of two lines, for the long ones.
    const titleX = MARGIN + 4.7;
    const titleW = SLIDE.w - MARGIN - titleX;

    text(slide, '{{criterion}}', { x: titleX, y: MARGIN - 0.15, w: titleW, h: 0.4 }, {
        fontSize: 18, color: COLOR.gold, bold: true, objectName: 'criterion',
    });
    text(slide, '{{circle}}', { x: titleX, y: MARGIN + 0.25, w: titleW, h: 1.2 }, {
        fontSize: 40, bold: true, color: COLOR.title, objectName: 'circle',
    });
    text(slide, '{{supervisor_line}}', { x: titleX, y: MARGIN + 1.45, w: titleW, h: 0.5 }, {
        fontSize: 22, color: COLOR.muted, objectName: 'supervisor',
    });
    text(slide, '{{course}}  {{period}}', { x: MARGIN, y: MARGIN + 1.45, w: titleX - MARGIN - 0.2, h: 0.5 }, {
        fontSize: 15, color: COLOR.muted, align: 'left', objectName: 'course and period',
    });
}

function addSlot(pres, slide, slot, box, columns) {
    const type = typography(columns, box.h);
    const pad = Math.min(0.25, box.h * 0.25) + (box.h >= 1.9 ? 0.2 : 0);
    const medal = box.h >= 1.9 ? 1.4 : Math.min(box.h - 0.2, 0.95);

    // "slot2 name", or "slot2@1 medal" for a shape shown with one place only.
    const name = (part, place = null) => `slot${slot}${place === null ? '' : `@${place}`} ${part}`;

    // The place, in a medal at the right end of the card (where an Arabic line starts).
    const medalX = box.x + box.w - pad - medal;
    const medalY = box.y + (box.h - medal) / 2;

    for (const look of cardLooks(slot)) {
        slide.addShape(pres.shapes.ROUNDED_RECTANGLE, {
            ...box,
            rectRadius: 0.12,
            fill: { color: look.fill },
            line: { color: look.line, width: look.lineWidth },
            objectName: name('card', look.place),
        });
    }

    for (const look of medalLooks(slot)) {
        slide.addShape(pres.shapes.OVAL, {
            x: medalX, y: medalY, w: medal, h: medal,
            fill: { color: look.color },
            line: { color: look.color, width: 0 },
            objectName: name('medal', look.place),
        });
    }

    text(slide, `{{place${slot}}}`, { x: medalX, y: medalY, w: medal, h: medal }, {
        fontSize: Math.round(type.name * 0.85), bold: true, color: COLOR.medalText, align: 'center', rtlMode: false,
        objectName: name('place'),
    });

    const left = box.x + pad;
    const right = medalX - (box.h >= 1.9 ? 0.35 : 0.2);

    if (type.stacked) {
        // One box, so that a name long enough to take two lines pushes its score down instead of covering it.
        const line = { fontFace: FONT, bold: true, rtlMode: true, lang: 'ar-SY', align: 'right' };

        slide.addText([
            { text: `{{name${slot}}}`, options: { ...line, fontSize: type.name, color: COLOR.text, breakLine: true } },
            { text: `{{score${slot}}}`, options: { ...line, fontSize: type.score, color: COLOR.gold } },
        ], {
            x: left, y: box.y, w: right - left, h: box.h,
            valign: 'middle', margin: 0, isTextBox: true, objectName: name('name and score'),
        });

        return;
    }

    // The score takes what "صفحة واحدة" needs; the name takes all the rest.
    const scoreW = type.score * 0.095;

    text(slide, `{{name${slot}}}`, { x: left + scoreW + 0.15, y: box.y, w: right - left - scoreW - 0.15, h: box.h }, {
        fontSize: type.name, bold: true, objectName: name('name'),
    });
    text(slide, `{{score${slot}}}`, { x: left, y: box.y, w: scoreW, h: box.h }, {
        fontSize: type.score, bold: true, color: COLOR.gold, align: 'left', objectName: name('score'),
    });
}

async function build(slots) {
    const pres = new PptxGenJS();
    pres.layout = 'LAYOUT_WIDE';
    pres.rtlMode = true;
    pres.title = 'أوائل الحلقات';
    pres.subject = 'قالب شرائح أوائل الحلقات';
    pres.author = 'نظام إدارة الجامع';
    pres.company = 'نظام إدارة الجامع';
    pres.theme = { headFontFace: FONT, bodyFontFace: FONT };

    const slide = pres.addSlide();
    slide.background = { color: COLOR.background };

    addHeader(pres, slide);

    const { columns, box } = layout(slots);

    for (let slot = 1; slot <= slots; slot++) {
        addSlot(pres, slide, slot, box(slot), columns);
    }

    const file = path.join(__dirname, `leaders-${slots}.pptx`);
    await pres.writeFile({ fileName: file });

    return file;
}

(async () => {
    for (let slots = 1; slots <= MAX_SLOTS; slots++) {
        console.log(await build(slots));
    }
})();
