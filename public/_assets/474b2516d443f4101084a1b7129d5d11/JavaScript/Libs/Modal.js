import $ from 'jquery'
import Bootbox from './Bootbox.js'
import Api from './Api.js'
import Common from './Common.js'
import Alpine from '@nng/nnrestapi/vendor/alpine.esm.js'

/**
 * Simple helper for opening Modals
 * 
 * ```
 * import Modal from './Libs/Modal.js'
 * 
 * // basic usage
 * const modal = Modal.factory('https://api.unrare.me/api/')
 * modal.showProfile({uid:1})
 * 
 * // inside of the AlpineJS controller
 * function myController( config ) {
 * 	return {
 * 		modal: Modal.factory(config.apiUrl)
 * 	}
 * }
 * 
 * // ... and then from the template
 * <button @click.stop="modal.showProfile(profile)">Show profile</button>
 * ```
 */
class Modal 
{
	api = null
	
	static factory(apiUrl) {
		const instance = new Modal()
		instance.api = Api.factory(apiUrl)
		return instance
	}

	/**
	 * Show a HTML-snippet
	 * ```
	 * Modal.showHtml('<b>nice!</b>')
	 * Modal.showHtml('.my-html')
	 * ```
	 * @param {string} selector
	 * @returns {Promise}
	 */
	async showHtml( selector, size = 'sm', buttons = false ) {
		return new Promise(async (resolve, reject) => {

			await Alpine.nextTick()
			const $html = $(selector).clone()

			Bootbox.dialog({
				className: `modal-unrare size-${size}`,
				message: Common.removeAlpineJsAttributes($html[0].outerHTML),
				size,
				buttons: buttons || {
					ok: {
						label: 'Schließen',
						className: 'btn-default',
						callback: function(){
							resolve()
						}
					},
				},
			})
		})
	}
	
	/**
	 * Show a AlpineJS component
	 * ```
	 * Modal.showAlpineComponent('my-component')
	 * ```
	 * @param {string} selector
	 * @returns {Promise}
	 */
	async showAlpineComponent( selector, size = 'sm', buttons = false ) {
		return new Promise(async (resolve, reject) => {

			await Alpine.nextTick()
			const $html = $(selector)
			const $parent = $html.parent().closest('[x-data]')

			await Bootbox.dialog({
				className: `modal-unrare size-${size}`,
				message: $html[0].outerHTML,
				size,
				backdrop: true,
				buttons: buttons || {
					ok: {
						label: 'Close',
						className: 'btn-default',
						callback: function(){
							resolve()
						}
					},
				},
				onShow: function(event) {
					const modal = event.target
					$(modal).appendTo($parent)
					Alpine.initTree(modal)
				},
				onShown: function (event) {
					const modal = event.target
					Alpine.nextTick(() => {
						modal.querySelectorAll?.('div.code-toolbar')?.forEach((wrapper) => {
							const pre = wrapper.querySelector('pre')
							if (!pre) return
							wrapper.replaceWith(pre)
						})
						window.Prism?.highlightAllUnder?.(modal)
					})
				}
			})
		})
	}

	/**
	 * Close the modal
	 * 
	 * @returns {void}
	 */
	close() {
		Bootbox.hideAll()
	}

	/**
	 * Generic buttons used in modal
	 * 
	 * @returns {object}
	 */
	get buttons() {
		return {
			close: (func) => {
				return {
					label: '<i class="fa fa-times text-black me-1"></i> Close',
					className: 'btn-default mx-3 my-3',
					callback: () => {
						func?.()
						this.close()
					}
				}
			},
			cancel: (func) => {
				return {
					label: '<i class="fa fa-times text-black me-1"></i> Abort',
					className: 'btn-default mx-3 my-3',
					callback: () => {
						func?.()
						this.close()
					}
				}
			},
			ok: (func) => {
				return {
					label: '<i class="fa fa-check text-white me-1"></i> Save',
					className: 'btn-success me-3 my-3',
					callback: () => {
						const result = func?.()
						if (result !== false) {
							this.close()
						}
						return result
					}
				}
			}
		}
	}

}

export default Modal