<div class="offcanvas offcanvas-end" tabindex="-1" id="notifikasi" aria-labelledby="notifikasiLabel">
    <div class="offcanvas-header bg-dark">
        <div class="offcanvas-title d-flex align-items-center" id="notifikasiLabel">
            <h5 class="text-light mb-0">Notifikasi</h5>

            <div class="ms-3">
                <button type="button" class="btn btn-outline-light btn-sm btnSettingNotif" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="fal fa-ellipsis-h-alt"></span>
                </button>
                <ul class="dropdown-menu">
                    <li><button class="dropdown-item tandaiSemuaTerbaca" type="button">Tandai semua sudah dibaca</button></li>
                </ul>
            </div>
        </div>

        <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="list-group list-group-flush overflow-auto" id="notifDisini"></div>
</div>
