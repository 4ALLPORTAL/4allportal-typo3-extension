import $ from 'jquery'
import Alpine from '@nng/nnrestapi/vendor/alpine.esm.js'
import Notification from '@typo3/backend/notification.js'
import Api from '../Libs/Api.js'
import Modal from '../Libs/Modal.js'
import Dialog from '../Libs/Dialog.js'
import Store from '../Libs/Store.js'
import Common from '../Libs/Common.js'
import EventBus from '../Libs/EventBus.js'

document.addEventListener('alpine:init', function setupAlpineBindings() {
	Alpine.data('alpineModLogger', LoggerController)
})

/**
 * Main Controller for the AlpineJS application
 * Reads config from data-config attribute to avoid CSP eval issues
 * 
 * @returns 
 */
function LoggerController( config ) 
{
    return {

		parseBool: Common.parseBool,
		copyToClipboard: Common.copyToClipboard,
		
		FIELDS: {
			info: { 
				label: '',
				alias: 'status',
			},
			datetime: { 
				label: 'Time' 
			},
			iphash: { 
				label: 'IP ',
				disabled: config.extConf.logIpMode === 'none',
			},
			duration: { 
				label: 'ms' 
			},
			method: { 
				label: 'Method' 
			},
			uri: { 
				label: 'Uri' 
			},
			target: { 
				label: 'Endpoint' 
			},
			status: { 
				label: 'Status' 
			},
			code: { 
				label: 'Code' 
			},
			error: { 
				label: 'Error'
			},
			feuser: { 
				label: 'Fe-User'
			},
			queryparams: { 
				label: 'Query'
			},
			payload: { 
				label: 'Payload',
				disabled: !config.logger.logPayload,
			},
		},

		config: config,
		api: Api.factory(config.apiUrl),
		modal: Modal.factory(config.apiUrl),
		store: Store.factory('logger'),

		logger: {
			enabled: config.logger.tempEnabled || config.logger.enabled,
		},

		loading: false,
		scrollContainer: null,
		columnDropdownOpen: false,

		rowHeight: 30,
        visibleRows: [],
        startRow: 0,
        endRow: 0,
		topSpacerHeight: 0,
        bottomSpacerHeight: 0,
		topSpacerStyle: 'height: 0px',
		bottomSpacerStyle: 'height: 0px',

		selectedLog: {},

		// results from backend
		results: {
			logs: [],
		},

		// flag to indicate if there are more results to load
		hasMore: true,

		// search in which field
		search: {
			field: '',
			keyword: '',
		},

		// filters from UI - initialized from store in init()
		filter: {
			limit: 100,
			offset: 0,
			sortBy: 'datetime',
			sort: 'DESC',
			errors: 0,
		},

		/**
		 * called on initialization
		 * Reads config from data-config attribute to avoid CSP eval issues
		 * 
		 * @returns {void}
		 */
		async init() {
			// Restore filter settings from store (localStorage)
			this.filter = this.store.get('filter', this.filter)
			this.search = this.store.get('search', this.search)
			const hiddenColumns = this.store.get('columns', {})
			for (let key in hiddenColumns) {
				this.FIELDS[key].hidden = hiddenColumns[key]
			}
			this.filter.offset = 0

			this.scrollContainer = document.querySelector('.logger-result')
			document.querySelector('.show-logs')?.addEventListener('shown.bs.tab', () => {
				this.loadMore(true)
			})

			// Refresh logs when window gains focus (if sorting by datetime desc)
			window.addEventListener('focus', () => {
				if (this.filter.sortBy === 'datetime' && this.filter.sort === 'DESC') {
					this.loadMore(true)
				}
			})

			// called after the user submits a request from the testbed
			EventBus.on('request.sent', (data) => {
				this.loadMore(true)
			})
		},

		/**
		 * Persist current filter settings to store
		 */
		saveFilter() {
			this.store.set('filter', this.filter)
			this.store.set('search', this.search)
			const hiddenColumns = {}
			Object.keys(this.FIELDS).forEach((key) => {
				hiddenColumns[key] = this.FIELDS[key].hidden === true
			})
			this.store.set('columns', hiddenColumns)
		},

		/**
		 * Clear search
		 */
		clearSearch() {
			this.search = {
				field: '',
				keyword: '',
			}
			this.loadMore(true)
		},

		/**
		 * Toggle column dropdown
		 * 
		 * @returns {void}
		 */
		toggleColumnDropdown() 
		{
			this.columnDropdownOpen = !this.columnDropdownOpen
		},
		/**
		 * Toggle column visibility
		 * 
		 * @param {*} key 
		 */
		toggleColumnVisibility(key) 
		{
			this.FIELDS[key].hidden = !this.FIELDS[key].hidden
			this.saveFilter()
		},

		/**
		 * Load more results (infinite scroll)
		 * 
		 * @returns {Promise}
		 */
		async loadMore( reset = false )
		{			
			this.saveFilter()

			if (this.parseBool(this.config.logger.disableDefaultEndpoints)) {
				return
			}

			if (reset) {
				this.filter.offset = 0
				this.results.logs = []
				this.hasMore = true
				this.scrollContainer.scrollTop = 0
			} else {
				this.filter.offset += this.filter.limit
			}

			if (this.loading || !this.hasMore) return

			const criteria = {
				...this.filter,
			}
			criteria[this.search.field || 'keyword'] = this.search.keyword

			this.loading = true
			const result = await this.api.GET('be/logs', criteria)

			this.results.logs = [...this.results.logs, ...result.results]
			this.hasMore = result.results.length >= this.filter.limit

			this.loading = false

			this.$nextTick(() => {
				this.calculateVisibleRows()
			})
		},

		/**
		 * Called when filter results are loaded
		 * 
		 * @returns {void}
		 */
		onFilterResultsLoaded() 
		{
			this.calculateVisibleRows()
			this.scrollContainer.scrollTop = 0
		},

		/**
		 * Sort by column
		 * 
		 * @param {string} column
		 * @returns {void}
		 */
		onSortBy(column) {
			if (this.FIELDS[column].sortable === false) {
				return
			}
			if (this.FIELDS[column].alias) {
				column = this.FIELDS[column].alias
			}
			if (this.filter.sortBy === column) {
				this.filter.sort = this.filter.sort === 'ASC' ? 'DESC' : 'ASC'
			} else {
				this.filter.sortBy = column
				this.filter.sort = 'DESC'
			}
			this.loadMore(true)
		},

		/**
		 * Get the IP mode label
		 * 
		 * @returns {string}
		 */
		getIpModeLabel() {
			return {
				anonymized: 'short',
				hashed: 'hash',
			}[this.config.extConf.logIpMode] || ''
		},

		/**
		 * 
		 * @param {*} status
		 * @returns 
		 */
		getIconClassForStatus(status) {
			if (status < 300) {
				return 'fa-check-circle'
			}
			const icons = {
				400: 'fa-exclamation-circle',
				403: 'fa-times-circle',
				404: 'fa-question-circle',
				500: 'fa-stop-circle',
			}
			return icons[status] || icons[400]
		},
		
		/**
		 * 
		 * @param {*} status
		 * @returns 
		 */
		getColorClassForStatus(status) 
		{
			if (status < 300) {
				return 'success'
			}
			const colors = {
				400: 'warning',
				403: 'warning',
				404: 'warning',
				500: 'danger',
			}
			return colors[status] || colors[400]
		},

		/**
		 * Delete logs
		 * 
		 * @returns {Promise}
		 */
		async clearLogs() {
			Dialog
				.confirm({
					title:	'Delete logs?', 
					html:	'All logs will be deleted. This cannot be undone.', 
					label:	'Delete',
				}).then( result => {
					if (!result) return
					this.api.DELETE(`be/logs`).then(data => {
						this.results.logs = []
						this.loadMore(true)
					})
				})
		},

		/**
		 * format the datetime:
		 * - remove date if it is today
		 * 
		 * @param {string} datetime
		 * @returns {string}
		 */
		formatDateTime(datetime) 
		{
			return new Date(datetime).toLocaleString()
		},
		
		/**
		 * Replay the request?
		 * 
		 * Set the request parameters to the form so 
		 * the user can submit it
		 * 
		 * @param {object} log
		 * @param {Event} event
		 * @returns {void}
		 */
		replayRequest(log, event) 
		{
			const uri = log.queryparams ? `${log.uri}?${log.queryparams}` : log.uri
			
			$('.reqtype').val(log.method).change()
			$('.requrl').val(uri)
			$('.reqbody').val(log.payload || '')
			
			Common.pumpAnimation('.request-form, .compose')
			this.modal.close()
		},

		/**
		 * Show log details
		 * 
		 * @param {object} log
		 * @param {Event} event
		 * @returns {void}
		 */
		showDetails(log, event) 
		{
			this.selectedLog = log
			this.modal.showAlpineComponent('.logger-details', 'md', {})
		},

		/**
		 * Toggle temporary logging
		 * 
		 * @returns {Promise}
		 */
		toggleTemporaryLogging() 
		{
			const enabled = this.logger.enabled

			if (enabled) {
				const title = {
					all: 'Logging all requests, except if disabled by @Api\\Log(false)',
					explicit: 'Logging requests enabled with @Api\\Log()',
					force: 'Logging all requests',
				}[this.config.extConf.loggingMode]
				Notification.success(
					title, 
					`Requests will be logged for ${this.config.extConf.loggingTempDuration} minutes.
					You can activate permanent and logging type in the extension manager.`, 
					7
				)
			} else {
				Notification.info(
					'Temporarily logging disabled', 
					'Requests will no longer be logged.', 
					3
				)
			}

			this.api.POST(`be/settings`, {
				temporaryLogging: enabled,
			})
		},

		/**
		 * Get the full URL for a log entry
		 * including the GET params
		 * 
		 * @param {object} log
		 * @returns {string}
		 */
		getFullUrl(log) {
			const urlBase = this.config.urlBase.replace(/\/$/, '')
			const uri = log.queryparams ? `${log.uri}?${log.queryparams}` : log.uri
			return `${urlBase}${uri}`
		},

		/**
		 * Get list of filtered profiles
		 * 
		 * @returns {array}
		 */
		get filteredLogs() {
			return this.results.logs
		},

		/**
		 * Called on scroll
		 * 
		 * @returns {void}
		 */
        onScroll() 
		{
            this.calculateVisibleRows()

			// Load more when near bottom
			const container = this.scrollContainer
			const scrollBottom = container.scrollHeight - container.scrollTop - container.offsetHeight
			if (scrollBottom < 200) {
				this.loadMore()
			}
        },

		/**
		 * Reduce visible rows to the current viewport
		 * 
		 * @returns {void}
		 */
		calculateVisibleRows() 
		{
			const totalRows = this.filteredLogs.length			
			const container = this.scrollContainer
			
			const scrollTop = container.scrollTop || 0
			const viewportHeight = container.offsetHeight

            const startRow = Math.floor(scrollTop / this.rowHeight)
            const endRow = Math.min(
                startRow + Math.ceil((viewportHeight) / this.rowHeight),
                totalRows
            )

			this.visibleRows = this.filteredLogs.slice(startRow, endRow)
            this.topSpacerHeight = startRow * this.rowHeight
            this.bottomSpacerHeight = (totalRows - endRow) * this.rowHeight
			this.topSpacerStyle = 'height: ' + this.topSpacerHeight + 'px'
			this.bottomSpacerStyle = 'height: ' + this.bottomSpacerHeight + 'px'
        },
	}
}

// make sure, Alpine is only initialized once per window
if (!window.Alpine) {
	window.Alpine = Alpine
	Alpine.start()
}