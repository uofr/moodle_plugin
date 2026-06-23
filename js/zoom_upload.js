// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for handling chunked Zoom media uploads and status polling.
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('videoFile');
    const queue = document.getElementById('uploadQueue');

    //  Memory object to store files in case of network failure ---
    const activeUploads = {}; 
   
    // Trigger the restore on page load
    const pendingDataEl = document.getElementById('pendingTransfersData');
    if (pendingDataEl && pendingDataEl.dataset.pending) {
        try {
            const pendingTransfers = JSON.parse(pendingDataEl.dataset.pending);
            pendingTransfers.forEach(transfer => {
                restorePendingUpload(transfer.clip_id, transfer.title);
            });
        } catch (e) {
            console.error("Error parsing pending transfers:", e);
        }
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                // Loop through all files
                Array.from(this.files).forEach(fileData => {
                    createNewUploadCard(fileData);
                });
                fileInput.value = ''; // Reset input so we can upload again
            }
        });
    }

    function createNewUploadCard(fileData) {
        const cardId = 'upload_' + Date.now() + Math.random().toString(36).substr(2, 5);
        
        // Save the file data to memory for potential retries
        activeUploads[cardId] = fileData; 

        const cardHTML = `
            <div id="${cardId}" class="card mb-3 shadow-sm border-0 d-block" style="transition: all 0.3s ease;">
                <div class="card-body">
                    <p class="font-weight-bold mb-2 text-truncate">${fileData.name}</p>
                    <div class="progress" style="height: 15px; border-radius: 6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%;">0%</div>
                    </div>
                    <div class="status-container mt-2">
                        <small class="text-muted status-text d-block">Preparing...</small>
                    </div>
                </div>
            </div>`;
        queue.insertAdjacentHTML('afterbegin', cardHTML);
        startUpload(fileData, cardId);
    }

    // ---  Global Retry Function for Network Drops ---
    window.retryNetworkUpload = function(cardId) {
        const fileData = activeUploads[cardId];
        if (!fileData) {
            alert("File data lost. Please re-drag the file into the upload zone.");
            return;
        }

        // Reset UI back to starting state
        const card = document.getElementById(cardId);
        const progressBar = card.querySelector('.progress-bar');
        const statusText = card.querySelector('.status-text');

        progressBar.classList.replace('bg-danger', 'bg-success');
        progressBar.style.width = '0%';
        progressBar.innerText = '0%';
        statusText.innerHTML = '<small class="text-muted status-text d-block">Retrying...</small>';

        // Restart the upload process
        startUpload(fileData, cardId);
    };
    // ----------------------------------------------------

    async function startUpload(fileData, cardId) {
        const card = document.getElementById(cardId);
        const progressBar = card.querySelector('.progress-bar');
        const statusText = card.querySelector('.status-text');

        const chunkSize = 2 * 1024 * 1024; // 2MB chunks
        const totalChunks = Math.ceil(fileData.size / chunkSize);
        
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end = Math.min(start + chunkSize, fileData.size);
            const chunk = fileData.slice(start, end);
            
            // Convert chunk to Base64
            const base64Chunk = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result);
                reader.readAsDataURL(chunk);
            });

            try {
                const response = await fetch(M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify([{
                        index: 0,
                        methodname: 'mod_zoomvideo_process_local_chunk', 
                        args: {
                            filename: fileData.name,
                            chunk_data: base64Chunk,
                            is_last_chunk: (i === totalChunks - 1)
                        }
                    }])
                });

                const json = await response.json();
                const result = json[0].data;

                // Update UI Progress
                const percent = Math.round(((i + 1) / totalChunks) * 100);
                progressBar.style.width = percent + '%';
                progressBar.innerText = percent + '%';

                // If last chunk, hand off to polling
                if (i === totalChunks - 1) {
                    if (result.clip_id) {
                        const finalizeData = new FormData();
                        finalizeData.append('clip_id', result.clip_id);
                        finalizeData.append('title', fileData.name);
                        finalizeData.append('sesskey', M.cfg.sesskey);

                        await fetch(M.cfg.wwwroot + '/local/mymedia/finalize_upload.php', {
                            method: 'POST',
                            body: finalizeData
                        });

                        // Clear from memory since upload is done, save RAM
                        delete activeUploads[cardId];

                        initiatePolling(cardId, result.clip_id);
                    } else {
                        throw new Error("Upload failed to return clip_id");
                    }
                }
            } catch (err) {
                console.error("Chunk Error:", err);
                
                // --- Network Error UI & Retry Button ---
                progressBar.classList.replace('bg-success', 'bg-danger');
                progressBar.innerText = "Error";
                statusText.innerHTML = `
                    <div class="d-flex align-items-center mt-1">
                        <span class="text-danger font-weight-bold mr-3"><i class="fa fa-exclamation-triangle"></i> Network error.</span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="retryNetworkUpload('${cardId}')">
                            <i class="fa fa-refresh"></i> Retry Upload
                        </button>
                    </div>
                `;
                return; // Stop the loop
               
            }
        }
    }

    function initiatePolling(cardId, clipId) {
        const card = document.getElementById(cardId);
        const progressContainer = card.querySelector('.progress');
        const statusContainer = card.querySelector('.status-container');

        progressContainer.style.display = 'none';
        statusContainer.innerHTML = `
            <small class="text-success font-weight-bold mr-2">
                <i class="fa fa-check"></i> Upload complete.
            </small>
            <small class="text-info font-weight-bold">
                <i class="fa fa-spinner fa-spin"></i> Adding to your media library...
            </small>
        `;
        const pollInterval = setInterval(() => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey, true);
            xhr.setRequestHeader('Content-Type', 'application/json');

            const payload = JSON.stringify([{
                index: 0,
                methodname: 'local_mymedia_get_transfer_status',
                args: { clip_id: String(clipId) }
            }]);

            xhr.onload = function() {
                try {
                    const responseArray = JSON.parse(xhr.responseText);
                    const serviceResponse = responseArray[0];

                    if (serviceResponse.error) {
                        console.error("Moodle Service Error:", serviceResponse.message);
                        statusContainer.innerHTML = `<small class="text-danger">Error: ${serviceResponse.errorcode}</small>`;
                        clearInterval(pollInterval);
                        return;
                    }

                    const data = serviceResponse.data;
                    
                    if (data && data.status) {
                        const status = data.status;

                        if (status === 'SUCCESS') {
                            clearInterval(pollInterval);
                            
                            card.classList.remove('border-0');
                            card.classList.add('border-success');
                            card.style.backgroundColor = '#f4fbf5'; 
                            
                            statusContainer.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-success font-weight-bold" style="font-size: 0.95rem;">
                                        <i class="fa fa-check-circle"></i> Video ready!
                                    </span>
                                    <button type="button" class="close text-success" aria-label="Close" onclick="this.closest('.card').remove()" style="opacity: 0.8;">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>`;
                                
                        } else if (status === 'FAILED') {
                            clearInterval(pollInterval);
                            
                            card.classList.remove('border-0');
                            card.classList.add('border-danger');
                            card.style.backgroundColor = '#fdf4f5'; 
                            
                            statusContainer.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-danger font-weight-bold" style="font-size: 0.95rem;">
                                        <i class="fa fa-exclamation-circle"></i> Processing failed. Please Re upload the video again.
                                    </span>
                                    <button type="button" class="close text-danger" aria-label="Close" onclick="this.closest('.card').remove()" style="opacity: 0.8;">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>`;
                        }
                    }
                } catch (e) {
                    console.error("Polling JSON Parse Error:", e);
                }
            };
            xhr.send(payload);
        }, 5000);
    }

    function restorePendingUpload(clipId, fileName) {
        const cardId = 'resume_' + clipId; 
        
        const cardHTML = `
            <div id="${cardId}" class="card mb-3 shadow-sm border-0 d-block" style="transition: all 0.3s ease;">
                <div class="card-body">
                    <p class="font-weight-bold mb-2 text-truncate">${fileName}</p>
                    <div class="progress" style="display: none;"></div> 
                    <div class="status-container mt-2">
                        <small class="text-success font-weight-bold mr-2">
                            <i class="fa fa-check"></i> Upload complete.
                        </small>
                        <small class="text-info font-weight-bold">
                            <i class="fa fa-spinner fa-spin"></i> Adding to your media library...
                        </small>
                    </div>
                </div>
            </div>`;
            
        queue.insertAdjacentHTML('beforeend', cardHTML);
        initiatePolling(cardId, clipId);
    }
});