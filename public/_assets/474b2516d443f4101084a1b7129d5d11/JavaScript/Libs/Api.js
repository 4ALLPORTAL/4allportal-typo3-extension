import Common from './Common.js'

/**
 * Simple helper for making requests to the backend-API
 * ```
 * const api = Api.factory('https://api.unrare.me/api/')
 * api.GET('sync/data').then(...)
 * ```
 * 
 */
class Api 
{
	apiUrl = ''
	
	static factory(apiUrl) {
		const instance = new Api()
		instance.apiUrl = apiUrl
		return instance
	}

	/**
	 * Send a GET request to the API
	 * 
	 * @param {string} endpoint
	 * @param {Object} srcParams
	 * @returns {Promise}
	 */
	GET(endpoint = '', srcParams = {}) 
	{
		// replace `true` and `false` values with `1` and `0` 
		const params = Object.fromEntries(
			Object.entries(srcParams).map(([k, v]) => [k, v === true ? 1 : v === false ? 0 : v])
		)

		const queryString = new URLSearchParams(params).toString()
		const url = queryString ? `${this.apiUrl}${endpoint}?${queryString}` : `${this.apiUrl}${endpoint}`
		return new Promise((resolve, reject) => {
			fetch( url )
				.then(async response => {
					const text = await response.text()
					if (!response.ok) {
						return reject(JSON.parse(text))
					}
					return text
				})
				.then(data => {
					if (data.substring(0,1) == '{' || data.substring(0,1) == '[') {
						return resolve(JSON.parse(data))
					}
					resolve( data )
				})
				.catch(error => {
					console.error('Error:', error)
					reject( data )
				})
		})
	}

	/**
	 * Send a POST request to the API
	 * 
	 * @param {string} endpoint
	 * @param {object} srcParams
	 * @returns {Promise}
	 */
	POST(endpoint = '', srcParams = {}) 
	{
		return new Promise((resolve, reject) => {

			let formData = new FormData()
			
			let params = Common.clone(srcParams)

			this.replaceFileReferencesRecursive(params, formData)
			formData.append('json', JSON.stringify(params))
			
			fetch( `${this.apiUrl}${endpoint}`, 
				{
					method: 'POST',
					body: formData
				})
				.then(async response => {
					const text = await response.text()
					if (!response.ok) {
						return reject(JSON.parse(text))
					}
					return text
				})
				.then(data => {
					if (data.substring(0,1) == '{' || data.substring(0,1) == '[') {
						const result = JSON.parse(data)
						return resolve(result)
					}
					resolve(data)
				})
				.catch(error => {
					console.error('Error:', error)
					reject( error )
				})
		})
	}

	/**
	 * Send a POST request but force download of the result
	 * 
	 * @param {string} endpoint 
	 * @param {Object} srcParams 
	 * @returns {Promise} 
	 */
	POST_DOWNLOAD(endpoint = '', srcParams = {}) 
	{
		return new Promise((resolve, reject) => {

			let formData = new FormData()
			let params = Common.clone(srcParams)
	
			this.replaceFileReferencesRecursive(params, formData)
			formData.append('json', JSON.stringify(params))
	
			fetch(`${this.apiUrl}${endpoint}`, {
				method: 'POST',
				body: formData
			})
			.then(response => {
				const disposition = response.headers.get('Content-Disposition')
				let filename = 'download'
				const filenameMatch = disposition && disposition.match(/filename="?([^";]+)"?/)
				if (filenameMatch && filenameMatch[1]) {
					filename = filenameMatch[1]
				}

				response.blob().then(blob => {
					const downloadUrl = URL.createObjectURL(blob)
					const link = document.createElement('a')
					link.href = downloadUrl
					link.download = filename
					document.body.appendChild(link)
					link.click()

					setTimeout(() => {
						URL.revokeObjectURL(downloadUrl)
						document.body.removeChild(link)
					}, 100)

					resolve({ status: 'download', filename })
				})
			})
			.catch(error => {
				console.error('Error:', error)
				reject(error)
			})
		})
	}

	/**
	 * Send a DELETE request to the API
	 * 
	 * @param {string} endpoint
	 * @param {object} srcParams
	 * @returns {Promise}
	 */
	DELETE(endpoint = '') 
	{
		return new Promise((resolve, reject) => {			
			fetch( `${this.apiUrl}${endpoint}`, 
				{
					method: 'DELETE'
				})
				.then(async response => {
					const text = await response.text()
					if (!response.ok) {
						return reject(JSON.parse(text))
					}
					return text
				})
				.then(data => {
					if (data.substring(0,1) == '{' || data.substring(0,1) == '[') {
						return resolve(JSON.parse(data))
					}
					resolve( data )
				})
				.catch(error => {
					console.error('Error:', error)
					reject( data )
				})
		})
	}

	/**
	 * Recursively parse an object and replace `File` objects
	 * in the data with the placeholder needed in the RestApi to
	 * parse the files correctly (e.g. `UPLOAD:/file-0`)
	 *
	 * @param {object} data
	 * @param {FormData} formData
	 * @returns {object}
	 */
	replaceFileReferencesRecursive(data, formData, n = 0) {

		let replaced = {}
		for (let key in data) {
			const value = data[key]

			if (Common.isFile(value) || Common.isBase64DataString(value)) {
				const placeholder = `relation-${n}`
				replaced[placeholder] = value
				formData.append(placeholder, value)
				data[key] = `UPLOAD:/${placeholder}`
				n++
				continue
			}

			if (Common.isObject(value) || Array.isArray(value)) {
				const result = this.replaceFileReferencesRecursive(value, formData, n)
				n += Object.keys(result).length
			}
		}

		return replaced
	}

}

export default Api