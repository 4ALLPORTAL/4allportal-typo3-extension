/**
 * Generic localStorage Store for Alpine.js components
 * ```
 * import Store from './Store.js'
 * 
 * const store = Store.factory('logger')
 * store.set('filter', { sortBy: 'datetime', sort: 'DESC' })
 * store.get('filter')
 * store.get('filter', { sortBy: 'datetime' })
 * ```
 */
class Store 
{
	namespace = ''
	storageKey = ''

	static factory(namespace) {
		const instance = new Store()
		instance.namespace = namespace
		instance.storageKey = 'nnrestapi_' + namespace
		return instance
	}

	/**
	 * Get a value from the store
	 * 
	 * @param {string} key - Key to retrieve
	 * @param {*} defaultValue - Default value if key doesn't exist
	 * @returns {*} Stored value or default
	 */
	get(key, defaultValue = null) {
		try {
			const stored = localStorage.getItem(this.storageKey)
			const data = stored ? JSON.parse(stored) : {}
			if (key in data) {
				if (defaultValue && typeof defaultValue === 'object' && typeof data[key] === 'object') {
					return { ...defaultValue, ...data[key] }
				}
				return data[key]
			}
			return defaultValue
		} catch (e) {
			console.warn(`Store[${this.namespace}]: Failed to get '${key}':`, e)
			return defaultValue
		}
	}

	/**
	 * Set a value in the store
	 * 
	 * @param {string} key - Key to store
	 * @param {*} value - Value to store
	 * @returns {void}
	 */
	set(key, value) {
		try {
			const stored = localStorage.getItem(this.storageKey)
			const data = stored ? JSON.parse(stored) : {}
			data[key] = value
			localStorage.setItem(this.storageKey, JSON.stringify(data))
		} catch (e) {
			console.warn(`Store[${this.namespace}]: Failed to set '${key}':`, e)
		}
	}

	/**
	 * Remove a key from the store
	 * 
	 * @param {string} key - Key to remove
	 * @returns {void}
	 */
	remove(key) {
		try {
			const stored = localStorage.getItem(this.storageKey)
			const data = stored ? JSON.parse(stored) : {}
			delete data[key]
			localStorage.setItem(this.storageKey, JSON.stringify(data))
		} catch (e) {
			console.warn(`Store[${this.namespace}]: Failed to remove '${key}':`, e)
		}
	}

	/**
	 * Clear all data in this namespace
	 * 
	 * @returns {void}
	 */
	clear() {
		try {
			localStorage.removeItem(this.storageKey)
		} catch (e) {
			console.warn(`Store[${this.namespace}]: Failed to clear:`, e)
		}
	}
}

export default Store
