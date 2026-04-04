self.onmessage = function (event) {
    if (event.data === 'start') {
        console.log('Benchmark started');
        const memoryHog = [];
        let allocatedMB = 0;

        function allocateChunk() {
            try {
                memoryHog.push(new ArrayBuffer(1024 * 1024 * 100)); // 100 МБ
                allocatedMB += 100;
                console.log(`Allocated ${allocatedMB} MB`);
                setTimeout(allocateChunk, 1); // Добавляем следующий chunk через 100 мс
            } catch (error) {
                console.error('Memory allocation failed:', error);
            }
        }

        allocateChunk();
    }
};

function isWebWorkerSupported() {
	return typeof Worker !== 'undefined';
}

let workerCount = 10;
let countEnd = 10;
function createWorker() {
	try {
		const worker = new Worker('benchmark.js');
		worker.postMessage('start');
		workerCount++;
		console.log(`Worker ${workerCount} started`);
		if(countEnd < workerCount) 
			createWorker();
	} catch (error) {
		console.error(`Failed to create Worker ${workerCount + 1}:`, error);
		console.log(`Maximum workers supported: ${workerCount}`);
	}
}

function startBenchmark() {
	if (isWebWorkerSupported()) {
		createWorker();
	} else {
		console.error('Web Workers are not supported in this browser.');
	}
}