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

    function load(duration = 500, timeout = 30000) {
        return new Promise((resolve) => {
            let frameCount = 0;
            let startTime = performance.now();
            let lastTime = startTime;
            const samples = [];
            let timeoutId = null;
            let rafId = null;
            let isActive = true;
            let hiddenStartTime = null;

            function checkFPS(currentTime) {
                if (!isActive) {
                    rafId = requestAnimationFrame(checkFPS);
                    return;
                }

                frameCount++;
                
                const delta = currentTime - lastTime;
                
                if (delta >= 1000) {
                    const fps = Math.round((frameCount * 1000) / delta);
                    samples.push(fps);
                    
                    frameCount = 0;
                    lastTime = currentTime;
                    
                    // Если есть хотя бы один измеренный FPS и прошло достаточно времени
                    if (samples.length > 0 && (currentTime - startTime >= duration)) {
                        cleanup();
                        const median = calculateMedian(samples);
                        resolve(median);
                        return;
                    }
                }
                
                rafId = requestAnimationFrame(checkFPS);
            }

            function handleVisibilityChange() {
                if (document.hidden) {
                    // Страница скрыта - приостанавливаем измерения
                    isActive = false;
                    hiddenStartTime = performance.now();
                } else {
                    // Страница снова видна - возобновляем измерения
                    isActive = true;
                    
                    // Корректируем временные метки для компенсации времени паузы
                    if (hiddenStartTime !== null) {
                        const hiddenDuration = performance.now() - hiddenStartTime;
                        startTime += hiddenDuration;
                        lastTime += hiddenDuration;
                        hiddenStartTime = null;
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
            }

            // Добавляем обработчик изменения видимости страницы
            document.addEventListener('visibilitychange', handleVisibilityChange);

            // Таймаут для случаев, когда FPS == 0 или измерения не начинаются
            timeoutId = setTimeout(() => {
                cleanup();
                const currentSamples = samples.filter(sample => sample > 0);
                const result = calculateMedian(currentSamples);
                resolve(result);
            }, timeout);

            rafId = requestAnimationFrame(checkFPS);
        });
    }

    exports.load = load;
    
    return exports;
})({});