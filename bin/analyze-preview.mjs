import { readFileSync } from 'node:fs';

for (const file of ['public/preview-contacts.html', 'public/preview-sales.html']) {
    const html = readFileSync(file, 'utf8');
    const count = (re) => (html.match(re) ?? []).length;
    console.log(file, {
        length: html.length,
        chartDivs: count(/x-data="DataCanvasChart/g),
        snapshots: count(/wire:snapshot/g),
        appAsset: count(/app-BVO9wcKn\.js/g),
        livewireScripts: count(/livewire\.js|@livewireScripts|livewireScriptConfig/gi),
    });
}

const sales = readFileSync('public/preview-sales.html', 'utf8');
const matches = [...sales.matchAll(/x-data="DataCanvasChart\(JSON\.parse\('([^']*)'\)\)"/g)];
console.log('sales x-data expressions:', matches.length);
matches.forEach((match, index) => {
    try {
        const parsed = JSON.parse(match[1]);
        console.log(index, 'ok', parsed.chart_type, parsed.title);
    } catch (error) {
        console.log(index, 'FAIL', error.message, match[1].slice(0, 120));
    }
});
