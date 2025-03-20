define([
	'jquery',
	'ko',
	'uiComponent'
], function ($, ko, Component) {
	'use strict';
	
	return Component.extend({
		defaults: {
			template: 'CravenDunnill_ProductNavCollectionThumbnail/collection-navigation'
		},
		
		/**
		 * Initialize component
		 */
		initialize: function () {
			this._super();
			
			// Enable horizontal scrolling with mouse drag
			$(document).on('ready', function () {
				const slider = document.querySelector('.collection-products');
				
				if (slider) {
					let isDown = false;
					let startX;
					let scrollLeft;

					slider.addEventListener('mousedown', (e) => {
						isDown = true;
						slider.classList.add('active');
						startX = e.pageX - slider.offsetLeft;
						scrollLeft = slider.scrollLeft;
					});

					slider.addEventListener('mouseleave', () => {
						isDown = false;
						slider.classList.remove('active');
					});

					slider.addEventListener('mouseup', () => {
						isDown = false;
						slider.classList.remove('active');
					});

					slider.addEventListener('mousemove', (e) => {
						if (!isDown) return;
						e.preventDefault();
						const x = e.pageX - slider.offsetLeft;
						const walk = (x - startX) * 2;
						slider.scrollLeft = scrollLeft - walk;
					});
				}
			});
		}
	});
});