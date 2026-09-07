/**
 * What the server tells the browser about a listing. Written once by PHP, read
 * by every class here — so the shape below is the PHP/JavaScript contract, and
 * the only place it can be checked.
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
 * @property {string[]} attributes              fields a hit may return
 * @property {Record<string, string[]>} sorts   key to engine sort expressions
 * @property {Record<string, string>} params    taxonomy to URL parameter
 * @property {Record<string, string>} reserved  names of sort, query and page
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
