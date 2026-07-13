const DB_NAME = 'retailpos-offline';
const STORE_BOOTSTRAP = 'bootstrap';
const STORE_QUEUE = 'queue';

const openDatabase = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STORE_BOOTSTRAP)) db.createObjectStore(STORE_BOOTSTRAP);
        if (!db.objectStoreNames.contains(STORE_QUEUE)) db.createObjectStore(STORE_QUEUE, { keyPath: 'offline_uuid' });
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

const transaction = async (store, mode, callback) => {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const request = callback(db.transaction(store, mode).objectStore(store));
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
};

const all = async (store) => {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const request = db.transaction(store, 'readonly').objectStore(store).getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
};

export const createPosOfflineStore = ({ bootstrapUrl, syncUrl, csrf }) => {
    const deviceId = localStorage.getItem('retailpos.pos.device_id') || crypto.randomUUID();
    localStorage.setItem('retailpos.pos.device_id', deviceId);

    const bootstrap = async () => {
        if (!navigator.onLine) return transaction(STORE_BOOTSTRAP, 'readonly', (store) => store.get('current'));
        const response = await fetch(bootstrapUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Bootstrap refresh failed');
        const snapshot = await response.json();
        await transaction(STORE_BOOTSTRAP, 'readwrite', (store) => store.put(snapshot, 'current'));
        return snapshot;
    };

    const queueBill = async (bill) => transaction(STORE_QUEUE, 'readwrite', (store) => store.put(bill));
    const pending = () => all(STORE_QUEUE);
    const pendingCount = async () => (await pending()).length;
    const customer = async (mobile) => {
        const snapshot = await transaction(STORE_BOOTSTRAP, 'readonly', (store) => store.get('current'));
        return snapshot?.customers?.find((entry) => entry.mobile === mobile) || null;
    };
    const products = async (term = '') => {
        const snapshot = await transaction(STORE_BOOTSTRAP, 'readonly', (store) => store.get('current'));
        const normalized = term.toLowerCase();
        return (snapshot?.products || []).filter((product) => !normalized || [product.name, product.sku, product.barcode].some((value) => value?.toLowerCase().includes(normalized)));
    };
    const sync = async () => {
        if (!navigator.onLine) return { offline: true, results: [] };
        const records = await pending();
        if (!records.length) return { results: [] };
        const response = await fetch(syncUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ batch_uuid: crypto.randomUUID(), device_id: deviceId, records }) });
        if (!response.ok) throw new Error('Offline sync failed');
        const payload = await response.json();
        await Promise.all((payload.results || []).filter((result) => ['synced', 'warning', 'duplicate'].includes(result.status)).map((result) => transaction(STORE_QUEUE, 'readwrite', (store) => store.delete(result.offline_uuid))));
        return payload;
    };

    return { deviceId, bootstrap, queueBill, pendingCount, customer, products, sync };
};
