
import $ from 'jquery'

const $testbed = $('.testbed')
const urlBase = $testbed.data().urlBase
const requestTypesWithBody = ['post', 'put', 'patch']

export {
	urlBase,
	requestTypesWithBody
}
