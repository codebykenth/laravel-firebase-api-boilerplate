<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Service for handling file operations in Google Cloud Storage
 * Provides methods for uploading, updating, and deleting files
 */
class FileService
{
    /**
     * Upload a file to Google Cloud Storage
     *
     * @param \Illuminate\Http\UploadedFile $file The file to upload
     * @param string $folderPath The path within the bucket to store the file
     * @param string|null $referenceId Optional reference ID (e.g., product ID) to organize files
     * @return string|null URL to the uploaded file, or null if upload failed
     */
    public function uploadFile($file, $folderPath, $referenceId = null)
    {
        $fileUrl = null;
        if ($file) {
            // Create unique filename using timestamp
            $fileName = time() . $file->getClientOriginalName();

            // Store the file in Google Cloud Storage
            $storeFile = $file->storeAs("$folderPath/$referenceId", $fileName, "gcs");

            // Make the file publicly accessible
            Storage::disk('gcs')->setVisibility($storeFile, 'public');

            // Get the public URL for the file
            $fileUrl = Storage::disk('gcs')->url($storeFile) ?? null;
        }
        return $fileUrl;
    }

    /**
     * Update an existing file with a new one
     *
     * @param string $url URL of the existing file to replace
     * @param \Illuminate\Http\UploadedFile $file New file to upload
     * @param string $folderPath The path within the bucket for file storage
     * @param string|null $referenceId Optional reference ID to organize files
     * @return string URL to the updated file, or original URL if update failed
     */
    // public function updateFile($url, $file, $folderPath, $referenceId = null)
    // {
    //     $fileUrl = $url;

    //     if ($file) {
    //         // Delete the old file if it exists
    //         $this->deleteFile($url, $folderPath, $referenceId);

    //         // Upload the new file
    //         $fileUrl = $this->uploadFile($file, $folderPath, $referenceId);
    //     }

    //     return $fileUrl;
    // }

    /**
     * Delete a file from Google Cloud Storage
     *
     * @param string $url URL of the file to delete
     * @param string $folderPath The path within the bucket where the file is stored
     * @param string|null $referenceId Optional reference ID used when organizing files
     * @return bool True if deletion was successful, false otherwise
     */
    public function deleteFile(string $url, string $folderPath, string $referenceId = null)
    {
        // Skip if URL is empty
        if (!$url) {
            return false;
        }

        // Extract the filename from the URL
        $fileName = $this->getFileNameFromUrl($url);

        // Construct the full storage path
        $path = "$folderPath/$referenceId/$fileName";

        // Check if the file exists before attempting deletion
        if (Storage::disk('gcs')->exists($path)) {
            return Storage::disk('gcs')->delete($path);
        }

        return false;
    }

    /**
     * Extract a filename from a full URL
     *
     * @param string $url The complete URL including the filename
     * @return string The extracted filename
     */
    public function getFileNameFromUrl($url)
    {
        // Parse the URL to get just the path component
        $path = parse_url($url, PHP_URL_PATH);

        // Extract the filename from the path and URL-decode it
        return basename(urldecode($path));
    }
}