/**
 * EventBus - Simple event delegation between JS scripts
 * 
 * Usage:
 * ```
 * import EventBus from '../Libs/EventBus.js'
 * 
 * // Listen to an event
 * EventBus.on('myevent', (data) => console.log(data))
 * 
 * // Unlisten
 * EventBus.off('myevent', handler)
 * 
 * // Emit an event
 * EventBus.emit('myevent', {params})
 * ```
 */
class EventBus 
{
	constructor() {
		this.events = {}
	}

	/**
	 * Subscribe to an event
	 * 
	 * @param {string} event - Event name
	 * @param {Function} callback - Callback function
	 * @returns {EventBus}
	 */
	on(event, callback) {
		if (!this.events[event]) {
			this.events[event] = []
		}
		this.events[event].push(callback)
		return this
	}

	/**
	 * Unsubscribe from an event
	 * 
	 * @param {string} event - Event name
	 * @param {Function} callback - Callback function to remove
	 * @returns {EventBus}
	 */
	off(event, callback) {
		if (!this.events[event]) {
			return this
		}
		this.events[event] = this.events[event].filter(cb => cb !== callback)
		return this
	}

	/**
	 * Emit an event
	 * 
	 * @param {string} event - Event name
	 * @param {*} data - Data to pass to callbacks
	 * @returns {EventBus}
	 */
	emit(event, data) {
		if (!this.events[event]) {
			return this
		}
		this.events[event].forEach(callback => callback(data))
		return this
	}

	/**
	 * Subscribe to an event only once
	 * 
	 * @param {string} event - Event name
	 * @param {Function} callback - Callback function
	 * @returns {EventBus}
	 */
	once(event, callback) {
		const onceCallback = (data) => {
			callback(data)
			this.off(event, onceCallback)
		}
		return this.on(event, onceCallback)
	}
}

const instance = new EventBus()
export default instance
