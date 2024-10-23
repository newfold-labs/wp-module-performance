window.addEventListener( 'load', () => {
	/* check if the browser supports Prefetch */
	const testlink = document.createElement("link"),
	supportsPrefetchCheck = testlink.relList && testlink.relList.supports && testlink.relList.supports("prefetch"),
	/* check if the user has set a reduced data usage option on the user agent or if the current connection effective type is 2g */  
	navigatorConnectionCheck = navigator.connection && (navigator.connection.saveData || (navigator.connection.effectiveType || "").includes("2g")),
	intersectionObserverCheck = window.IntersectionObserver && "isIntersecting" in IntersectionObserverEntry.prototype;
	
	if ( ! supportsPrefetchCheck || navigatorConnectionCheck ) {
		return;
	} else {
		class LP_APP {
			constructor(config) {
				this.config = config;
				this.activeOnDesktop = config.activeOnDesktop;
				this.behavior = config.behavior;
				this.hoverDelay = config.hoverDelay;
				this.ignoreKeywords = config.ignoreKeywords.split(',');
				this.instantClick = config.instantClick;
				this.mobileActive = config.mobileActive;
				this.isMobile = config.isMobile;
				this.mobileBehavior = config.mobileBehavior;
				this.prefetchedUrls = new Set();
				this.timerIdentifier;
				this.eventListenerOptions = { capture: !0, passive: !0 };
			}
			/**
			 * Init
			 * @returns {void}
			 */	
			init() {
				const isChrome = navigator.userAgent.indexOf("Chrome/") > -1,
					  chromeVersion = isChrome && parseInt(navigator.userAgent.substring(navigator.userAgent.indexOf("Chrome/") + "Chrome/".length));
				
				if ( isChrome && chromeVersion < 110 ) {return;}
				if ( this.isMobile && ! this.mobileActive ) {return;}
				if ( ! this.isMobile && ! this.activeOnDesktop ) {return;}
	
				if ( ! this.isMobile ) {
					if ( 'mouseHover' === this.behavior ) {
						let hoverDelay = parseInt(this.hoverDelay);
						hoverDelay = isNaN(hoverDelay) ? 60 : hoverDelay;
						document.addEventListener("mouseover", this.mouseHover.bind(this), this.eventListenerOptions);
					} else if ( 'mouseDown' === this.behavior ) {
						if ( this.instantClick ) {
							document.addEventListener("mousedown", this.mouseDownToClick.bind(this), this.eventListenerOptions);
						} else {
							document.addEventListener("mousedown", this.mouseDown.bind(this), this.eventListenerOptions)
						}
					}
				}

				if ( this.mobileActive ) {
					if ( 'touchstart' === this.mobileBehavior ) {
						document.addEventListener("touchstart", this.touchstart.bind(this), this.eventListenerOptions);
					} else if ( 'viewport' && intersectionObserverCheck ) {
						this.viewport();
					}
				}
			}
			/**
			 * Viewport handler
			 * @returns {void}
			 */				
			viewport() {
				const io = new IntersectionObserver((e) => {
					e.forEach((e) => {
						if (e.isIntersecting) {
							const n = e.target;
							io.unobserve(n);
							this.canPrefetch(n) && this.prefetchIt(n.href);
						}
					});
				});
				let requestIdleCallback =  window.requestIdleCallback ||
							function (cb) {
								var start = Date.now();
								return setTimeout(function () {
									cb({
									didTimeout: false,
									timeRemaining: function () {
										return Math.max(0, 50 - (Date.now() - start));
									}
									});
								}, 1);
							};
				requestIdleCallback( () => {
					return setTimeout(function () {
						return document.querySelectorAll("a").forEach(function (a) {
							return io.observe(a);
						});
					}, 1000);
				}, { timeout: 1000 });
			}
			/**
			 * Mouse Down handler
			 * @param {Event} e - listener event
			 * @returns {void}
			 */		
			mouseDown(e) {
				const el = e.target.closest("a");
				this.canPrefetch(el) && this.prefetchIt(el.href);
			}

			/**
			 * Mouse Down handler for instant click
			 * @param {Event} e - listener event
			 * @returns {void}
			 */	
			mouseDownToClick(e) {
				//if (performance.now() - o < r) return;
				const el = e.target.closest("a");
				if (e.which > 1 || e.metaKey || e.ctrlKey) return;
				if (!el) return;
				el.addEventListener(
					"click",
					function (t) {
						'lpappinstantclick' != t.detail && t.preventDefault();
					},
					{ capture: !0, passive: !1, once: !0 }
				);
				const n = new MouseEvent("click", { view: window, bubbles: !0, cancelable: !1, detail: 'lpappinstantclick' });
				el.dispatchEvent(n);
			}

			touchstart(e) {
				const el = e.target.closest("a");
				this.canPrefetch(el) && this.prefetchIt(el.href);
			}

			/**
			 * Clean Timers
			 * @param {Event} t - listener event
			 * @returns {void}
			 */	
			clean(t) {
				if ( t.relatedTarget && t.target.closest("a") == t.relatedTarget.closest("a") || this.timerIdentifier ) {
					clearTimeout( this.timerIdentifier );
					this.timerIdentifier = void(0);
				}
			}

			/**
			 * Mouse hover function
			 * @param {Event} e - listener event
			 * @returns {void}
			 */		
			mouseHover(e) {
				if ( !("closest" in e.target) ) return;
				const link = e.target.closest("a");
				if ( this.canPrefetch( link ) ) {
					link.addEventListener("mouseout", this.clean.bind(this), { passive: !0 });
					this.timerIdentifier = setTimeout(()=> {
						this.prefetchIt( link.href );
						this.timerIdentifier = void(0);
					}, this.hoverDelay);
				}
			}
	
			/**
			 * Can the url be prefetched or not
			 * @param {Element} el - link element
			 * @returns {boolean} - if it can be prefetched
			 */
			canPrefetch( el ) {
				if ( el && el.href ) {
					/* it has been just prefetched before */
					if (this.prefetchedUrls.has(el.href)) {
						return false;
					}

					/* avoid if it is the same url as the actual location */
					if ( el.href.replace(/\/$/, "") !== location.origin.replace(/\/$/, "") && el.href.replace(/\/$/, "") !== location.href.replace(/\/$/, "") ) {
						return true;
					}

					/* checking exclusions */
					const exclude = this.ignoreKeywords.filter( k => {
						if ( el.href.indexOf (k) > -1) {
							return k;
						}
					})
					if ( exclude.length > 0 ) { return false; }
					
				}
		
				return false;
			}

			/**
			 * Append link rel=prefetch to the head
			 * @param {string} url - url to prefetch
			 * @returns {void}
			 */
			prefetchIt(url) {
				const toPrefechLink = document.createElement("link");

				toPrefechLink.rel = "prefetch";
				toPrefechLink.href = url;
				toPrefechLink.as = "document";

				document.head.appendChild(toPrefechLink);
				this.prefetchedUrls.add(url);
			}
		}
		/* 
			default config:
			'activeOnDesktop' => true,
			'behavior'        =>'mouseHover',
			'hoverDelay'      => 60,
			'instantClick'    => true ,
			'activeOnMobile'  => true ,
			'mobileBehavior'  => 'viewport',
			'ignoreKeywords'  =>'wp-admin,#,?',
		*/
		const lpapp = new LP_APP( window.LP_CONFIG );
		lpapp.init();
	}
});
