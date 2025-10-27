/**
 * CPIU Frontend Multi-Product JavaScript
 * 
 * Enhanced frontend functionality for multi-product image upload
 * 
 * @package Custom_Product_Image_Upload
 * @since 1.2.0
 */

/* global Cropper, cpiu_params */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    
    // Check if required dependencies are loaded
    if (typeof Cropper === 'undefined' || typeof cpiu_params === 'undefined') {
        console.error('CPIU Error: Cropper.js or cpiu_params not loaded.');
        return;
    }

    // Get DOM elements
    const uploadButton = document.getElementById('cpiu-open-upload-modal');
    const uploadModal = document.getElementById('cpiu-upload-modal');
    const closeUploadModal = document.getElementById('cpiu-close-upload-modal');
    const doneButton = document.getElementById('cpiu-done-button');
    const fileInput = document.getElementById('cpiu_custom_images');
    const previewContainer = document.getElementById('cpiu-image-preview');
    const externalPreviewContainer = document.getElementById('cpiu-image-preview-external');
    const errorContainer = document.getElementById('cpiu-error-container');
    const cropperModal = document.getElementById('cpiu-cropper-modal');
    const imageToCrop = document.getElementById('cpiu-image-to-crop');
    const saveCropButton = document.getElementById('cpiu-save-cropped-image');
    const closeCropButton = document.getElementById('cpiu-close-cropper-modal');
    const addToCartButton = document.querySelector('form.cart .single_add_to_cart_button');
    const uploadProgressContainer = document.getElementById('cpiu-upload-progress');
    const progressBar = document.getElementById('cpiu-progress-bar');
    const progressText = document.getElementById('cpiu-progress-text');
    const uploadLoadingModal = document.getElementById('cpiu-upload-loading');
    const uploadStatusText = document.getElementById('cpiu-upload-status');

    // Debug: Check which elements exist
    console.log('CPIU Debug - uploadButton:', uploadButton);
    console.log('CPIU Debug - uploadModal:', uploadModal);
    console.log('CPIU Debug - closeUploadModal:', closeUploadModal);
    console.log('CPIU Debug - fileInput:', fileInput);
    console.log('CPIU Debug - previewContainer:', previewContainer);
    console.log('CPIU Debug - errorContainer:', errorContainer);

    // Validate essential elements exist
    if (!uploadButton || !uploadModal || !closeUploadModal) {
        console.error('CPIU Error: Modal elements missing. uploadButton:', !!uploadButton, 'uploadModal:', !!uploadModal, 'closeUploadModal:', !!closeUploadModal);
        return;
    }

    if (!fileInput || !previewContainer || !errorContainer) {
        console.error('CPIU Error: Upload elements missing. fileInput:', !!fileInput, 'previewContainer:', !!previewContainer, 'errorContainer:', !!errorContainer);
        return;
    }

    // External preview container is optional
    if (!externalPreviewContainer) {
        console.warn('CPIU Warning: External preview container not found. Preview will only show in modal.');
    }

    // Initialize variables
    let cropperInstance = null;
    let currentImageElement = null;
    let uploadedImageData = []; // Array of { originalSrc, currentSrc, element, wrapper }

    /**
     * Sync image preview between modal and external container
     */
    function syncImagePreview() {
        if (!externalPreviewContainer) return;
        
        // Clear external preview
        externalPreviewContainer.innerHTML = '';
        
        if (uploadedImageData.length === 0) {
            // Show no images message
            const noImagesMsg = document.createElement('div');
            noImagesMsg.className = 'cpiu-no-images-message';
            noImagesMsg.textContent = cpiu_params.text_no_images || 'No images uploaded yet. Click "Upload Images" above to add your images.';
            externalPreviewContainer.appendChild(noImagesMsg);
        } else {
            // Show uploaded images
            uploadedImageData.forEach((imageData, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'cpiu-image-wrapper';
                
                const imgElement = document.createElement('img');
                imgElement.src = imageData.currentSrc;
                imgElement.alt = `Uploaded image ${index + 1}`;
                imgElement.className = 'cpiu-preview-image';
                imgElement.title = cpiu_params.text_click_to_crop || 'Click to crop';
                
                const deleteBtn = document.createElement('button');
                deleteBtn.innerHTML = '&times;';
                deleteBtn.type = 'button';
                deleteBtn.className = 'cpiu-delete-btn';
                deleteBtn.ariaLabel = cpiu_params.text_delete_image || 'Delete image';
                deleteBtn.title = cpiu_params.text_delete_image || 'Delete image';
                
                // Add click handlers
                imgElement.addEventListener('click', () => handleOpenCropper(imageData));
                deleteBtn.addEventListener('click', () => handleDeleteImage(imageData));
                
                wrapper.appendChild(imgElement);
                wrapper.appendChild(deleteBtn);
                externalPreviewContainer.appendChild(wrapper);
            });
        }
    }

    // Modal functionality
    function openUploadModal() {
        console.log('CPIU Debug - Opening modal');
        uploadModal.classList.add('show');
        document.body.classList.add('cpiu-modal-open', 'cpiu-no-scroll');
    }

    function closeUploadModalFunc() {
        console.log('CPIU Debug - Closing modal');
        uploadModal.classList.remove('show');
        document.body.classList.remove('cpiu-modal-open', 'cpiu-no-scroll');
    }

    // Test modal functionality
    console.log('CPIU Debug - Testing modal functionality');
    console.log('CPIU Debug - Modal element exists:', !!uploadModal);
    console.log('CPIU Debug - Button element exists:', !!uploadButton);

    // Event listeners for modal
    console.log('CPIU Debug - Adding event listeners');
    uploadButton.addEventListener('click', function(e) {
        console.log('CPIU Debug - Upload button clicked');
        e.preventDefault();
        console.log('CPIU Debug - Modal element:', uploadModal);
        console.log('CPIU Debug - Modal classes:', uploadModal.className);
        openUploadModal();
        console.log('CPIU Debug - Modal classes after opening:', uploadModal.className);
    });
    closeUploadModal.addEventListener('click', function(e) {
        console.log('CPIU Debug - Close button clicked');
        e.preventDefault();
        closeUploadModalFunc();
    });
    
    // Add event listener for Done button
    if (doneButton) {
        doneButton.addEventListener('click', function(e) {
            console.log('CPIU Debug - Done button clicked');
            e.preventDefault();
            closeUploadModalFunc();
        });
    }
    
    // Close modal when clicking outside
    uploadModal.addEventListener('click', function(e) {
        if (e.target === uploadModal) {
            closeUploadModalFunc();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && uploadModal.classList.contains('show')) {
            closeUploadModalFunc();
        }
    });

    const requiredCount = parseInt(cpiu_params.required_image_count, 10);
    const maxFileSize = parseInt(cpiu_params.max_file_size, 10);
    const allowedTypes = cpiu_params.allowed_types || ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const resolutionValidation = cpiu_params.resolution_validation || false;
    const minWidth = parseInt(cpiu_params.min_width, 10) || 0;
    const minHeight = parseInt(cpiu_params.min_height, 10) || 0;
    const maxWidth = parseInt(cpiu_params.max_width, 10) || 0;
    const maxHeight = parseInt(cpiu_params.max_height, 10) || 0;

    // Disable add to cart button initially
    addToCartButton.disabled = true;

    // Add event listeners
    fileInput.addEventListener('change', handleFileSelection);
    saveCropButton.addEventListener('click', handleSaveCrop);
    closeCropButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        handleCloseCropper();
    });

    /**
     * Handle file selection
     */
    function handleFileSelection(event) {
        const files = event.target.files;
        if (!files || files.length === 0) return;

        // Show progress container
        uploadProgressContainer.classList.add('cpiu-show');
        progressBar.style.width = '0%';
        progressText.textContent = cpiu_params.text_loading;

        let filesProcessed = 0;
        const totalFiles = files.length;
        const newFilesArray = Array.from(files);

        newFilesArray.forEach(file => {
            // Validate file type
            if (!isValidFileType(file)) {
                progressText.textContent = cpiu_params.text_skipped_file.replace('{filename}', file.name);
                showFileTypeError(file.name);
                filesProcessed++;
                updateFileReadProgress(filesProcessed, totalFiles);
                return;
            }

            // Validate file size
            if (file.size > maxFileSize) {
                progressText.textContent = cpiu_params.error_file_size.replace('{filename}', file.name);
                showFileSizeError(file.name);
                filesProcessed++;
                updateFileReadProgress(filesProcessed, totalFiles);
                return;
            }

            // Validate image resolution
            validateImageResolution(file, (isValid, errorMessage) => {
                if (!isValid) {
                    progressText.textContent = errorMessage;
                    showResolutionError(errorMessage);
                    filesProcessed++;
                    updateFileReadProgress(filesProcessed, totalFiles);
                    return;
                }

                // If resolution is valid, proceed with file reading
                const reader = new FileReader();
                reader.onloadstart = () => {
                    progressText.textContent = cpiu_params.text_loading_file.replace('{filename}', file.name);
                };
                
                reader.onload = (e) => {
                    if (uploadedImageData.length < requiredCount) {
                        addImagePreview(e.target.result, file.name);
                    } else {
                        updateValidationError(cpiu_params.error_more_images);
                        console.warn(`CPIU: Max ${requiredCount} images. Skipped: ${file.name}`);
                    }
                    filesProcessed++;
                    updateFileReadProgress(filesProcessed, totalFiles);
                    validateImageCount();
                };
                
                reader.onerror = () => {
                    console.error(`CPIU: Error reading ${file.name}`);
                    progressText.textContent = cpiu_params.text_error_loading.replace('{filename}', file.name);
                    filesProcessed++;
                    updateFileReadProgress(filesProcessed, totalFiles);
                };
                
                reader.readAsDataURL(file);
            });
        });

        // Clear input
        fileInput.value = '';
    }

    /**
     * Validate file type
     */
    function isValidFileType(file) {
        const fileExtension = file.name.split('.').pop().toLowerCase();
        return allowedTypes.includes(fileExtension);
    }

    /**
     * Validate image resolution
     */
    function validateImageResolution(file, callback) {
        if (!resolutionValidation) {
            callback(true, null);
            return;
        }

        const img = new Image();
        
        img.onload = function() {
            // Clean up object URL
            URL.revokeObjectURL(img.src);
            
            const width = img.width;
            const height = img.height;
            let errorMessage = null;

            // Check minimum dimensions
            if (minWidth > 0 && width < minWidth) {
                errorMessage = cpiu_params.error_resolution_min_width
                    .replace('{filename}', file.name)
                    .replace('{width}', width)
                    .replace('{min_width}', minWidth);
            } else if (minHeight > 0 && height < minHeight) {
                errorMessage = cpiu_params.error_resolution_min_height
                    .replace('{filename}', file.name)
                    .replace('{height}', height)
                    .replace('{min_height}', minHeight);
            }
            // Check maximum dimensions
            else if (maxWidth > 0 && width > maxWidth) {
                errorMessage = cpiu_params.error_resolution_max_width
                    .replace('{filename}', file.name)
                    .replace('{width}', width)
                    .replace('{max_width}', maxWidth);
            } else if (maxHeight > 0 && height > maxHeight) {
                errorMessage = cpiu_params.error_resolution_max_height
                    .replace('{filename}', file.name)
                    .replace('{height}', height)
                    .replace('{max_height}', maxHeight);
            }

            callback(errorMessage === null, errorMessage);
        };
        
        img.onerror = function() {
            URL.revokeObjectURL(img.src);
            callback(false, cpiu_params.text_resolution_error || 'Could not load image for resolution validation.');
        };

        // Create object URL for the file
        const objectURL = URL.createObjectURL(file);
        img.src = objectURL;
    }

    /**
     * Update file read progress
     */
    function updateFileReadProgress(loaded, total) {
        if (!progressBar || !progressText) return;
        
        const percent = total > 0 ? Math.round((loaded / total) * 100) : 0;
        progressBar.style.width = percent + '%';
        progressText.textContent = cpiu_params.text_loaded_progress
            .replace('{loaded}', loaded)
            .replace('{total}', total)
            .replace('{percent}', percent);
        
        if (loaded >= total) {
            setTimeout(() => {
                if (uploadProgressContainer) {
                    uploadProgressContainer.classList.remove('cpiu-show');
                }
            }, 1500);
        }
    }

    /**
     * Add image preview
     */
    function addImagePreview(imageSrc, imageName) {
        if (!previewContainer) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'cpiu-image-wrapper-large';
        
        const imgElement = document.createElement('img');
        imgElement.src = imageSrc;
        imgElement.alt = imageName;
        imgElement.className = 'cpiu-preview-image-large';
        imgElement.title = cpiu_params.text_click_to_crop || 'Click to crop';
        
        const deleteBtn = document.createElement('button');
        deleteBtn.innerHTML = '&times;';
        deleteBtn.type = 'button';
        deleteBtn.className = 'cpiu-delete-btn-large';
        deleteBtn.ariaLabel = cpiu_params.text_delete_image || 'Delete image';
        deleteBtn.title = cpiu_params.text_delete_image || 'Delete image';
        
        wrapper.appendChild(imgElement);
        wrapper.appendChild(deleteBtn);
        previewContainer.appendChild(wrapper);
        
        const imageData = {
            originalSrc: imageSrc,
            currentSrc: imageSrc,
            element: imgElement,
            wrapper: wrapper
        };
        
        uploadedImageData.push(imageData);
        
        imgElement.addEventListener('click', () => handleOpenCropper(imageData));
        deleteBtn.addEventListener('click', () => handleDeleteImage(imageData));
        
        // Sync with external preview
        syncImagePreview();
    }

    /**
     * Handle opening cropper
     */
    function handleOpenCropper(imageData) {
        console.log('CPIU Debug - Opening cropper');
        if (!cropperModal || !imageToCrop || typeof Cropper === 'undefined') {
            console.error('CPIU Error - Cropper elements missing:', {
                cropperModal: !!cropperModal,
                imageToCrop: !!imageToCrop,
                Cropper: typeof Cropper
            });
            return;
        }
        
        currentImageElement = imageData.element;
        imageToCrop.src = imageData.currentSrc;
        
        // Use both class and style for maximum compatibility
        cropperModal.classList.add('show');
        
        console.log('CPIU Debug - Cropper modal display set to flex');
        
        if (cropperInstance) {
            cropperInstance.destroy();
        }
        
        // Small delay to ensure modal is visible before initializing cropper
        setTimeout(() => {
            try {
                cropperInstance = new Cropper(imageToCrop, {
                    aspectRatio: 1 / 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    background: false,
                    modal: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false
                });
                console.log('CPIU Debug - Cropper initialized successfully');
            } catch (error) {
                console.error('CPIU Error - Failed to initialize cropper:', error);
            }
        }, 100);
        
        if (addToCartButton) {
            addToCartButton.disabled = true;
        }
        updateValidationError('');
    }

    /**
     * Handle saving crop
     */
    function handleSaveCrop(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (!cropperInstance || !currentImageElement) {
            console.error("CPIU Error: Cropper/image ref missing.");
            return;
        }
        
        const canvas = cropperInstance.getCroppedCanvas({
            width: 500,
            height: 500,
            fillColor: '#fff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'medium'
        });
        
        if (canvas) {
            const croppedImageDataUrl = canvas.toDataURL('image/jpeg', 0.9);
            const imageDataIndex = uploadedImageData.findIndex(data => data.element === currentImageElement);
            
            if (imageDataIndex > -1) {
                uploadedImageData[imageDataIndex].element.src = croppedImageDataUrl;
                uploadedImageData[imageDataIndex].currentSrc = croppedImageDataUrl;
                
                // Sync with external preview
                syncImagePreview();
            } else {
                console.error("CPIU Error: Could not find image data to update.");
            }
            
            handleCloseCropper();
        } else {
            console.error("CPIU Error: Could not get cropped canvas.");
            alert(cpiu_params.text_crop_error || 'Could not crop image.');
        }
    }

    /**
     * Handle closing cropper
     */
    function handleCloseCropper() {
        console.log('CPIU Debug - Closing cropper');
        if (cropperModal) {
            cropperModal.classList.remove('show');
        }
        
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }
        
        currentImageElement = null;
        validateImageCount();
    }

    /**
     * Handle deleting image
     */
    function handleDeleteImage(imageData) {
        if (!imageData || !imageData.wrapper) return;
        
        imageData.wrapper.remove();
        uploadedImageData = uploadedImageData.filter(data => data !== imageData);
        validateImageCount();
        
        // Sync with external preview
        syncImagePreview();
    }

    /**
     * Validate image count
     */
    function validateImageCount() {
        const currentCount = uploadedImageData.length;
        let errorMessage = '';
        let isButtonDisabled = true;
        
        if (currentCount < requiredCount) {
            errorMessage = cpiu_params.error_less_images;
            isButtonDisabled = true;
        } else if (currentCount > requiredCount) {
            errorMessage = cpiu_params.error_more_images;
            isButtonDisabled = true;
        } else {
            errorMessage = '';
            isButtonDisabled = false;
        }
        
        updateValidationError(errorMessage);
        
        if (addToCartButton) {
            addToCartButton.disabled = isButtonDisabled;
            addToCartButton.removeEventListener('click', handleAddToCartSubmission);
            
            if (!isButtonDisabled) {
                // Remove existing event listener before adding new one
                addToCartButton.removeEventListener('click', handleAddToCartSubmission);
                addToCartButton.addEventListener('click', handleAddToCartSubmission);
            }
        }
    }

    /**
     * Update validation error
     */
    function updateValidationError(message) {
        if (errorContainer) {
            errorContainer.textContent = message;
            if (message) {
                errorContainer.classList.add('cpiu-show');
            } else {
                errorContainer.classList.remove('cpiu-show');
            }
        }
    }

    /**
     * Show file type error notice
     */
    function showFileTypeError(filename) {
        const errorMessage = cpiu_params.error_file_type.replace('{filename}', filename);
        showNotice(errorMessage, 'error');
    }

    /**
     * Show file size error notice
     */
    function showFileSizeError(filename) {
        const errorMessage = cpiu_params.error_file_size.replace('{filename}', filename);
        showNotice(errorMessage, 'error');
    }

    /**
     * Show resolution error notice
     */
    function showResolutionError(errorMessage) {
        showNotice(errorMessage, 'error');
    }

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        // Remove existing notices
        const existingNotices = document.querySelectorAll('.cpiu-notice');
        existingNotices.forEach(notice => notice.remove());

        // Create notice element
        const notice = document.createElement('div');
        notice.className = 'cpiu-notice cpiu-notice-' + type;
        notice.textContent = message;

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);

        // Add to page
        document.body.appendChild(notice);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.classList.add('cpiu-notice-exit');
                setTimeout(() => {
                    if (notice.parentNode) {
                        notice.remove();
                    }
                }, 300);
            }
        }, 5000);

        // Add click to dismiss
        notice.addEventListener('click', () => {
            if (notice.parentNode) {
                notice.classList.add('cpiu-notice-exit');
                setTimeout(() => {
                    if (notice.parentNode) {
                        notice.remove();
                    }
                }, 300);
            }
        });
    }

    /**
     * Handle add to cart submission
     */
    function handleAddToCartSubmission(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (uploadedImageData.length !== requiredCount) {
            validateImageCount();
            alert(cpiu_params.error_less_images);
            return;
        }
        
        // Show upload loading modal
        if (uploadLoadingModal) {
            uploadLoadingModal.classList.add('show');
        }
        
        // Initialize progress tracking
        updateUploadProgress(0, cpiu_params.text_progress_preparing || 'Preparing images for upload...');
        
        if (addToCartButton) {
            addToCartButton.disabled = true;
        }
        
        const imagesToSend = uploadedImageData.map(data => data.currentSrc);
        const totalImages = imagesToSend.length;
        
        // Update progress to show processing started
        const processingMessage = (cpiu_params.text_progress_processing || 'Processing {count} image{plural}...')
            .replace('{count}', totalImages)
            .replace('{plural}', totalImages > 1 ? 's' : '');
        updateUploadProgress(10, processingMessage);
        
        const formData = new FormData();
        formData.append('action', 'cpiu_upload_cropped_images');
        formData.append('images_data', JSON.stringify(imagesToSend));
        formData.append('security', cpiu_params.nonce);
        formData.append('product_id', cpiu_params.product_id);
        
        // Simulate progress during upload
        let progressInterval = setInterval(() => {
            const currentProgress = getCurrentProgress();
            if (currentProgress < 80) {
                const uploadingMessage = cpiu_params.text_progress_uploading || 'Uploading images to server...';
                updateUploadProgress(currentProgress + 5, uploadingMessage);
            }
        }, 200);
        
        fetch(cpiu_params.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            clearInterval(progressInterval);
            const serverProcessingMessage = cpiu_params.text_progress_server_processing || 'Processing server response...';
            updateUploadProgress(85, serverProcessingMessage);
            
            if (!response.ok) {
                return response.text().then(text => {
                    const statusErrorMessage = (cpiu_params.text_ajax_status_error || 'HTTP error! Status: {status}, Response: {text}')
                        .replace('{status}', response.status)
                        .replace('{text}', text);
                    throw new Error(statusErrorMessage);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const finalizingMessage = cpiu_params.text_progress_finalizing || 'Finalizing upload...';
                updateUploadProgress(95, finalizingMessage);
                
                setTimeout(() => {
                    const completeMessage = cpiu_params.text_progress_complete || 'Upload complete! Redirecting to cart...';
                    updateUploadProgress(100, completeMessage);
                    
                    setTimeout(() => {
                        if (data.cart_url || cpiu_params.cart_url) {
                            window.location.href = data.cart_url || cpiu_params.cart_url;
                        } else {
                            // Fallback: hide modal and re-enable button
                            if (uploadLoadingModal) {
                                uploadLoadingModal.classList.remove('show');
                            }
                            if (addToCartButton) {
                                addToCartButton.disabled = false;
                            }
                        }
                    }, 1000);
                }, 500);
            } else {
                clearInterval(progressInterval);
                const serverMessage = data.data?.message || cpiu_params.text_unknown_error || 'Unknown server error.';
                console.error('CPIU AJAX Error:', serverMessage, data);
                
                const errorMessage = (cpiu_params.text_progress_error || 'Error: {message}')
                    .replace('{message}', serverMessage);
                updateUploadProgress(0, errorMessage, true);
                
                setTimeout(() => {
                    if (uploadLoadingModal) {
                        uploadLoadingModal.classList.remove('show');
                    }
                    
                    if (addToCartButton) {
                        addToCartButton.disabled = false;
                    }
                    
                    alert(cpiu_params.text_ajax_error.replace('{message}', serverMessage));
                }, 2000);
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            console.error('CPIU Fetch Error:', error);
            
            const networkErrorMessage = (cpiu_params.text_progress_network_error || 'Network error: {message}')
                .replace('{message}', error.message);
            updateUploadProgress(0, networkErrorMessage, true);
            
            setTimeout(() => {
                if (uploadLoadingModal) {
                    uploadLoadingModal.classList.remove('show');
                }
                
                if (addToCartButton) {
                    addToCartButton.disabled = false;
                }
                
                alert(cpiu_params.text_ajax_network_error + ` (${error.message})`);
            }, 2000);
        });
    }

    /**
     * Update upload progress with visual feedback
     */
    function updateUploadProgress(percentage, message, isError = false) {
        if (uploadStatusText) {
            const color = isError ? '#e74c3c' : '#27ae60';
            uploadStatusText.innerHTML = `<p style="color: ${color}; margin: 0; font-weight: 500;">${message}</p>`;
        }
        
        // Update progress bar if it exists in the modal
        const modalProgressBar = document.querySelector('#cpiu-upload-loading .cpiu-progress-bar');
        if (modalProgressBar) {
            modalProgressBar.style.width = percentage + '%';
            modalProgressBar.style.backgroundColor = isError ? '#e74c3c' : (cpiu_params.progress_bar_color || '#007cba');
        }
        
        // Store current progress for interval tracking
        window.cpiuCurrentProgress = percentage;
    }

    /**
     * Get current progress percentage
     */
    function getCurrentProgress() {
        return window.cpiuCurrentProgress || 0;
    }

    // Apply dynamic colors
    applyDynamicColors();

    // Initial validation
    validateImageCount();

    /**
     * Apply dynamic colors to UI elements
     */
    function applyDynamicColors() {
        if (typeof cpiu_params === 'undefined' || !cpiu_params.progress_bar_color) {
            return;
        }

        const color = cpiu_params.progress_bar_color;
        
        // Apply color to progress bar
        const progressBar = document.getElementById('cpiu-progress-bar');
        if (progressBar) {
            progressBar.style.backgroundColor = color;
        }

        // Apply color to upload button
        const uploadButton = document.getElementById('cpiu-open-upload-modal');
        if (uploadButton) {
            uploadButton.style.backgroundColor = color;
            uploadButton.style.borderColor = color;
        }

        // Apply color to done button
        const doneButton = document.getElementById('cpiu-done-button');
        if (doneButton) {
            doneButton.style.backgroundColor = color;
            doneButton.style.borderColor = color;
        }

        // Apply color to save crop button
        const saveCropButton = document.getElementById('cpiu-save-cropped-image');
        if (saveCropButton) {
            saveCropButton.style.backgroundColor = color;
            saveCropButton.style.borderColor = color;
        }

        // Apply color to any elements with cpiu-dynamic-bg class
        const dynamicBgElements = document.querySelectorAll('.cpiu-dynamic-bg');
        dynamicBgElements.forEach(element => {
            element.style.setProperty('--cpiu-bg-color', color);
            element.style.backgroundColor = color;
        });

        // Apply color to any elements with cpiu-dynamic-border class (like spinners)
        const dynamicBorderElements = document.querySelectorAll('.cpiu-dynamic-border');
        dynamicBorderElements.forEach(element => {
            element.style.setProperty('--cpiu-border-color', color);
            element.style.borderTopColor = color;
        });
    }
});
