var FrameRateJS = (function (exports) {
    'use strict';

    function calculateMedian(values) {
        if (values.length === 0) return 0;
        
        const sorted = values.slice().sort((a, b) => a - b);
        const middle = Math.floor(sorted.length / 2);
        
        if (sorted.length % 2 === 0) {
            return Math.round((sorted[middle - 1] + sorted[middle]) / 2);
        } else {
            return sorted[middle];
        }
    }

    function load(duration = 500, timeout = 3000) {
        return new Promise((resolve) => {
            let frameCount = 0;
            let startTime = null;
            let lastTime = null;
            let samples = [];
            let timeoutId = null;
            let rafId = null;
            let isActive = true;

            function startMeasurement() {
                frameCount = 0;
                startTime = performance.now();
                lastTime = startTime;
                samples = [];
                isActive = true;

                rafId = requestAnimationFrame(checkFPS);
            }

            function stopMeasurement() {
                if (rafId) {
                    cancelAnimationFrame(rafId);
                    rafId = null;
                }
                isActive = false;
            }

            function checkFPS(currentTime) {
                if (!isActive) return;

                frameCount++;
                
                const delta = currentTime - lastTime;
                
                if (delta >= 1000) {
                    const fps = Math.round((frameCount * 1000) / delta);
                    samples.push(fps);
                    
                    frameCount = 0;
                    lastTime = currentTime;
                    
                    // Проверяем, набрали ли мы достаточное время измерений
                    const measurementTime = currentTime - startTime;
                    if (measurementTime >= duration) {
                        completeMeasurement();
                        return;
                    }
                }
                
                rafId = requestAnimationFrame(checkFPS);
            }

            function completeMeasurement() {
                cleanup();
                const median = calculateMedian(samples);
                resolve(median);
            }

            function handleVisibilityChange() {
                if (document.hidden) {
                    // Страница скрыта - останавливаем измерения
                    if (isActive) {
                        stopMeasurement();
                    }
                } else {
                    // Страница снова видна - перезапускаем измерения
                    if (!isActive) {
                        startMeasurement();
                    }
                }
            }

            function cleanup() {
                if (timeoutId) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
                if (rafId) {
                    cancelAnimationFrame(rafId);
                    rafId = null;
                }
                document.removeEventListener('visibilitychange', handleVisibilityChange);
                window.removeEventListener('beforeunload', handleBeforeUnload);
                window.removeEventListener('pagehide', handleBeforeUnload);
            }

            function handleBeforeUnload() {
                // Если страница выгружается, завершаем с текущими результатами
                cleanup();
                const currentSamples = samples.filter(sample => sample > 0);
                const result = currentSamples.length > 0 ? calculateMedian(currentSamples) : 0;
                resolve(result);
            }

            // Добавляем обработчики событий
            document.addEventListener('visibilitychange', handleVisibilityChange);
            window.addEventListener('beforeunload', handleBeforeUnload);
            window.addEventListener('pagehide', handleBeforeUnload);

            // Начинаем измерение только если страница видима
            if (!document.hidden) {
                startMeasurement();
            } else {
                // Ждем пока страница станет видимой
                const waitForVisible = () => {
                    if (!document.hidden) {
                        document.removeEventListener('visibilitychange', waitForVisible);
                        startMeasurement();
                    }
                };
                document.addEventListener('visibilitychange', waitForVisible);
            }
        });
    }

    exports.load = load;
    
    return exports;
})({});