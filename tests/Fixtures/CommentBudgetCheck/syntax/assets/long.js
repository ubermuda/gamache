// One
// Two
// Three
// Four
// Five
// Six
export const a = 1;

/**
 * A JSDoc block is exempt, however many annotations it carries.
 *
 * @param {string} name
 * @param {number} count
 * @param {object} options
 * @returns {string}
 */
export function b(name, count, options) {
    return name;
}

/*
 * A plain block comment is not exempt.
 * Three
 * Four
 * Five
 * Six
 */
export const c = 2;
