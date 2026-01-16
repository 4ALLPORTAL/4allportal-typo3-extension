import $ from 'jquery'

/**
 * Collection of static helpers for multiple purposes
 * 
 * ```
 * import Common from './Libs/Common.js'
 * ```
 */
class Common 
{
	/**
	 * Copy text to clipboard and animate the element
	 */
	static async copyToClipboard($el)
	{
		$el = $($el)
		const isInput = $el.is('input, textarea')
		const text = isInput ? ($el.val() ?? '') : $el.text()

		Common.pumpAnimation($el)

		if (!text && text !== 0) return false
		const str = String(text)
		try {
			if (navigator?.clipboard?.writeText) {
				await navigator.clipboard.writeText(str)
				return true
			}
		} catch (e) {
			// ignore
		}
		try {
			const textarea = document.createElement('textarea')
			textarea.value = str
			textarea.setAttribute('readonly', '')
			textarea.style.position = 'fixed'
			textarea.style.top = '-9999px'
			document.body.appendChild(textarea)
			textarea.select()
			const ok = document.execCommand('copy')
			document.body.removeChild(textarea)
			return ok
		} catch (e) {
			return false
		}
	}

	/**
	 * Pump animation
	 * 
	 * @param {jQuery} $el
	 */
	static pumpAnimation($el) 
	{
		$el = $($el)
		$el.removeClass('pump')

		setTimeout(() => {
			$el.addClass('pump')
		}, 10)
	}

	/**
	 * Returns boolean value
	 * ```
	 * Common.parseBool('1')
	 * ```
	 * @param {String|Number|Boolean} value
	 * @returns {Boolean}
	 */
	static parseBool(value) {
		return value === true || value === '1' || value === 1 || value === 'true' || value === 'on'
	}

	/**
	 * Returns `true` if the email is valid
	 * ```
	 * Common.isEmail('john@malone.com')
	 * ```
	 * @param {String}
	 * @returns {Boolean}
	 */
	static isEmail( email ) {
		return email.match(
			/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
		)
	}

	/**
	 * Format the currency
	 * ```
	 * // --> 10,50 €
	 * Common.formatCurrency(10.5)
	 * ```
	 * @param {String|Number} val
	 * @returns {String}
	 */
	static formatCurrency( val ) {
		return new Intl.NumberFormat('de-DE', { 
			style: 'currency', 
			currency: 'EUR' 
		}).format(val)
	}

	/**
	 * Get current UTC tstamp
	 * ```
	 * const tstamp = Common.tstamp
	 * ```
	 * @returns {int}
	 */
	static get tstamp() {
		const now = new Date()
		return now / 1000
	}

	/**
	 * Check if an object is a File
	 * ```
	 * let isObj = Common.isFile(obj)
	 * ```
	 * @param {mixed} obj
	 * @returns {boolean}
	 */
	static isFile(obj) {
		return obj instanceof File
	}

	/**
	 * Check if a string is a base64
	 * ```
	 * let isBase64 = Common.isFile(string)
	 * ```
	 * @param {mixed} obj
	 * @returns {boolean}
	 */
	static isBase64DataString(str) {
		const base64DataUrlRegex =
			/^data:([a-zA-Z0-9]+\/[a-zA-Z0-9.+-]+)?;base64,[A-Za-z0-9+/=]+$/

		return base64DataUrlRegex.test(str)
	}

	/**
	 * Check if an object is an object (and not an Array)
	 * Like `Array.isArray()` but for Objects.
	 * ```
	 * let isObj = Common.isObject(obj)
	 * ```
	 * @param {mixed} obj
	 * @returns {boolean}
	 */
	static isObject(obj) {
		return typeof obj === 'object' && obj !== null && !Array.isArray(obj)
	}

	/**
	 * Create a deep clone of an object.
	 * ```
	 * let clone = Utils.clone({some:{deep:'thing'}})
	 * ```
	 * @returns {mixed}
	 */
	static clone(value) {
        if (value === null || typeof value !== 'object') {
            return value
        }
        if (value instanceof File) {
            return new File([value], value.name, { type: value.type, lastModified: value.lastModified })
        }
        if (value instanceof Blob) {
            return new Blob([value], { type: value.type })
        }
        if (value instanceof Date) {
            return new Date(value.getTime())
        }
        if (value instanceof RegExp) {
            return new RegExp(value)
        }
        if (Array.isArray(value)) {
            return value.map(item => Common.clone(item))
        }
        const copiedObject = {}
        for (const key in value) {
            if (Object.prototype.hasOwnProperty.call(value, key)) {
                copiedObject[key] = Common.clone(value[key])
            }
        }
        return copiedObject
    }

	/**
	 * Remove all [x-data] or [:xxx] attributes from markup.
	 * Avoids AlpineJS trying to re-render or initialize the
	 * code when it is inserted in the DOM
	 * 
	 * @param {string} html
	 * @return {string}
	 */
	static removeAlpineJsAttributes(html) 
	{
		const $text = $(`<div>${html}</div>`)
		$text.find('*').each(function () {
			$.each(this.attributes, function (i, attr) {
				if (attr && (attr.name.startsWith('x-') || attr.name.startsWith(':'))) {
					$(this.ownerElement).removeAttr(attr.name)
				}
			})
		})
		return $text.html()
	}
}

export default Common