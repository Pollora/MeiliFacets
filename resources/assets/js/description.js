/**
 * The shape below is the PHP/JavaScript contract, written once here and once in `ListingDescription`.
 *
 * @typedef {object} FacetDescription
 * @property {string} taxonomy
 * @property {boolean} multiple  whether several values can be held at once
 * @property {number} cap        how many values a URL may carry for this facet
 *
 * @typedef {object} ListingDescription
 * @property {string} name
 * @property {string} filter                    clauses every query carries
 * @property {number} perPage
 * @property {number} reachableHits             hits the engine will serve past which no page exists
 * @property {string[]} attributes              fields a hit may return
 * @property {Record<string, string[]>} sorts   key to engine sort expressions
 * @property {Record<string, string>} params    taxonomy to URL parameter
 * @property {Record<string, string>} reserved  names of sort, query and page
 * @property {string} countPattern              singular and plural forms, separated by a pipe
 * @property {string} filterPattern             the same, for the count of active filters
 * @property {FacetDescription[]} facets
 * @property {'submit' | 'immediate'} apply
 *
 * @typedef {object} Connection
 * @property {string} url
 * @property {string} key
 * @property {string} index
 */

const FACET_FIELD_PREFIX = 'facets.'

/**
 * @param {FacetDescription} facet
 */
export const facetField = (facet) => FACET_FIELD_PREFIX + facet.taxonomy
