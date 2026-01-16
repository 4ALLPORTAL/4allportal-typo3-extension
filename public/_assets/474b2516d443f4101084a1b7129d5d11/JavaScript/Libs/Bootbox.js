/**
 * ES Module wrapper for Bootbox
 * Dynamically imports Bootstrap and injects $.fn.modal for Bootbox compatibility
 * Uses ES module jQuery
 */
import $ from 'jquery'
import bootboxFactory from '@nng/nnrestapi/vendor/bootbox.esm.js'

// Initialize bootbox with jQuery
const bootbox = bootboxFactory($)

// Dynamically import Bootstrap Modal and inject into $.fn.modal
let initialized = false

const ensureBootstrapModal = async () => {
	if (initialized) return
	
	const bootstrap = await import('@nng/nnrestapi/vendor/bootstrap.esm.min.js')
	
	// Inject Bootstrap Modal into jQuery.fn for Bootbox compatibility
	$.fn.modal = function(options) {
		return this.each(function() {
			const element = this
			const $element = $(element)
			let instance = bootstrap.Modal.getInstance(element)
			
			if (typeof options === 'string') {
				// Method call: 'show', 'hide', 'toggle', 'dispose'
				if (!instance) {
					instance = new bootstrap.Modal(element)
				}
				if (typeof instance[options] === 'function') {
					instance[options]()
				}
			} else {
				// Initialize with options
				if (!instance) {
					instance = new bootstrap.Modal(element, options || {})
					
					// Bridge Bootstrap 5 native events to jQuery events for Bootbox compatibility
					element.addEventListener('show.bs.modal', (e) => $element.trigger('show.bs.modal', e))
					element.addEventListener('shown.bs.modal', (e) => $element.trigger('shown.bs.modal', e))
					element.addEventListener('hide.bs.modal', (e) => $element.trigger('hide.bs.modal', e))
					element.addEventListener('hidden.bs.modal', (e) => $element.trigger('hidden.bs.modal', e))
				}
			}
		})
	}
	
	// Also add Modal constructor for direct access
	$.fn.modal.Constructor = bootstrap.Modal
	
	initialized = true
}

// Wrap dialog to ensure Bootstrap Modal is injected first
const originalDialog = bootbox.dialog
bootbox.dialog = async function(options) {
	await ensureBootstrapModal()
	return originalDialog.call(bootbox, options)
}

export default bootbox
