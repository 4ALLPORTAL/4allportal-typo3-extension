import $ from 'jquery'
import Bootbox from './Bootbox.js'
import Common from './Common.js'

/**
 * Simple helper for opening Dialog messages
 * ```
 * import Dialog from './Libs/Dialog.js'
 * ```
 */
class Dialog 
{	
	/**
	 * Dialog.confirm({title:'Ja?', html:'wirklich?', label:'Ja, ich will'}).then( result => {})
	 * 
	 * @returns {Promise}
	 */
	static confirm( settings ) 
	{
		return new Promise(async (resolve, reject) => {
			await Alpine.nextTick()
			Bootbox.confirm({
				title: settings.title,
				message: Common.removeAlpineJsAttributes(settings.html),
				buttons: {
					confirm: {
						label: settings.label || 'OK',
						className: 'btn-success'
					},
					cancel: {
						label: 'Abbrechen',
						className: 'btn-default'
					}
				},
				size: 'sm',
				callback: (result) => {
					resolve( result )
				}
			})
		})
	}
}

export default Dialog