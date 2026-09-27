import assert from 'node:assert/strict';
import { test } from 'node:test';
import { NAMING_GENERATED_DATA_END, NAMING_GENERATED_DATA_START } from '../lib.mjs';
import { scanLines } from '../naming.mjs';

// Assembled so that check:naming does not flag this test file for quoting a forbidden string.
const forbidden = 'wp-integration' + '-docs';
const rule = { id: 'legacy-docs-path', reason: 'Устаревший путь', replace: 'docs/', re: new RegExp(forbidden) };
const file = 'wp-content/mu-plugins/starter-core/config.generated.php';

test('scanLines: a forbidden string inside the naming-generated-data span is not flagged', () => {
  const lines = [
    `\t// ${NAMING_GENERATED_DATA_START}`,
    `\t\t'pattern' => '${forbidden}',`,
    `\t// ${NAMING_GENERATED_DATA_END}`,
  ];
  assert.deepEqual(scanLines(file, lines, [rule]), []);
});

test('scanLines: the same forbidden string elsewhere in the file is flagged', () => {
  const lines = [
    `\t// ${NAMING_GENERATED_DATA_START}`,
    `\t\t'pattern' => '${forbidden}',`,
    `\t// ${NAMING_GENERATED_DATA_END}`,
    `\t'legacy' => '${forbidden}',`,
  ];
  const errors = scanLines(file, lines, [rule]);
  assert.equal(errors.length, 1);
  assert.match(errors[0], new RegExp(`^${file}:4: forbidden "${forbidden}" \\[legacy-docs-path\\]`));
});

test('scanLines: naming:allow still skips its own line outside any span', () => {
  const lines = [`\t'legacy' => '${forbidden}', // naming:allow`];
  assert.deepEqual(scanLines(file, lines, [rule]), []);
});
