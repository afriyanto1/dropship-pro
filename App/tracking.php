<?php
require 'config/database.php';
require 'includes/functions.php';

$pageTitle = 'Cek Resi / Tracking';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Cek Resi Pengiriman</h1>
        <div class="text-muted">Lacak paket secara langsung melalui website resmi kurir ekspedisi.</div>
    </div>
</div>

<!-- Navigasi Tab Pilihan Kurir -->
<div class="card p-3 mb-4 shadow-sm border-0">
    <ul class="nav nav-pills gap-2 flex-wrap" id="trackingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="jne-tab" data-bs-toggle="pill" data-bs-target="#jne-content" type="button" role="tab">
                <i class="bi bi-truck me-1"></i> JNE Tracking
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="jnt-tab" data-bs-toggle="pill" data-bs-target="#jnt-content" type="button" role="tab">
                <i class="bi bi-truck me-1"></i> J&T Express
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="spx-tab" data-bs-toggle="pill" data-bs-target="#spx-content" type="button" role="tab">
                <i class="bi bi-truck me-1"></i> Shopee Xpress (SPX)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="anteraja-tab" data-bs-toggle="pill" data-bs-target="#anteraja-content" type="button" role="tab">
                <i class="bi bi-box-seam me-1"></i> Anteraja
            </button>
        </li>
    </ul>
</div>

<!-- Konten Iframe per Kurir -->
<div class="tab-content" id="trackingTabContent">
    <!-- JNE -->
    <div class="tab-pane fade show active" id="jne-content" role="tabpanel" aria-labelledby="jne-tab">
        <div class="card shadow-sm border-0 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-semibold text-primary">JNE Tracking Package</span>
                <a href="https://jne.co.id/tracking-package" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                </a>
            </div>
            <div class="ratio" style="--bs-aspect-ratio: 75%; min-height: 600px;">
                <iframe src="https://jne.co.id/tracking-package" class="rounded border" title="JNE Tracking"></iframe>
            </div>
            <div class="form-text mt-2 text-muted">* Catatan: Jika halaman kurir tampak kosong/gagal dimuat, hal tersebut dikarenakan kebijakan keamanan (*X-Frame-Options*) dari website resmi kurir. Silakan gunakan tombol "Buka di Tab Baru".</div>
        </div>
    </div>

    <!-- J&T -->
    <div class="tab-pane fade" id="jnt-content" role="tabpanel" aria-labelledby="jnt-tab">
        <div class="card shadow-sm border-0 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-semibold text-danger">J&T Express Track</span>
                <a href="https://www.jet.co.id/track" target="_blank" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                </a>
            </div>
            <div class="ratio" style="--bs-aspect-ratio: 75%; min-height: 600px;">
                <iframe src="https://www.jet.co.id/track" class="rounded border" title="J&T Tracking"></iframe>
            </div>
            <div class="form-text mt-2 text-muted">* Catatan: Jika halaman kurir tampak kosong/gagal dimuat, silakan gunakan tombol "Buka di Tab Baru".</div>
        </div>
    </div>

    <!-- Shopee Express -->
    <div class="tab-pane fade" id="spx-content" role="tabpanel" aria-labelledby="spx-tab">
        <div class="card shadow-sm border-0 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-semibold text-dark">Shopee Xpress (SPX)</span>
                <a href="https://spx.co.id/id" target="_blank" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                </a>
            </div>
            <div class="ratio" style="--bs-aspect-ratio: 75%; min-height: 600px;">
                <iframe src="https://spx.co.id/id" class="rounded border" title="Shopee Xpress Tracking"></iframe>
            </div>
            <div class="form-text mt-2 text-muted">* Catatan: Jika halaman kurir tampak kosong/gagal dimuat, silakan gunakan tombol "Buka di Tab Baru".</div>
        </div>
    </div>

    <!-- Anteraja -->
    <div class="tab-pane fade" id="anteraja-content" role="tabpanel" aria-labelledby="anteraja-tab">
        <div class="card shadow-sm border-0 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-semibold text-success">Anteraja Tracking</span>
                <a href="https://anteraja.id/id/tracking" target="_blank" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                </a>
            </div>
            <div class="ratio" style="--bs-aspect-ratio: 75%; min-height: 600px;">
                <iframe src="https://anteraja.id/id/tracking" class="rounded border" title="Anteraja Tracking"></iframe>
            </div>
            <div class="form-text mt-2 text-muted">* Catatan: Jika halaman kurir tampak kosong/gagal dimuat, silakan gunakan tombol "Buka di Tab Baru".</div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php';?>