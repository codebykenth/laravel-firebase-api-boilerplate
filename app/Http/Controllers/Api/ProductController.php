<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileService;
use App\Services\FirestoreService;
use Illuminate\Http\Request;

/**
 * Controller for handling Product-related operations
 * Uses Firestore for data persistence and Firebase Storage for image storage
 */
class ProductController extends Controller
{
    protected $firestore;
    protected $fileService;

    /**
     * Initialize the controller with required services
     *
     * @param FirestoreService $firestoreService Service for Firestore database operations
     * @param FileService $fileService Service for handling file uploads and management
     */
    public function __construct(FirestoreService $firestoreService, FileService $fileService)
    {
        $this->firestore = $firestoreService;
        $this->fileService = $fileService;
    }

    /**
     * Retrieve all products
     *
     * @return array List of all products from Firestore
     */
    public function index()
    {
        return $this->firestore->readDocuments('products');
    }

    /**
     * Create a new product
     *
     * @param Request $request The incoming request with product data and images
     * @return \Illuminate\Http\JsonResponse Response with created product data
     */
    public function store(Request $request)
    {
        // Validate incoming product data
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'images' => 'array|nullable',
            'images.*' => 'file|mimes:jpg,jpeg,png,gif|max:2048', // Validate each image file
        ]);

        // Handle file uploads if provided
        $fileUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                // Upload each image and store its URL
                $fileUrl = $this->fileService->uploadFile($image, 'products', $data['id']);

                if ($fileUrl) {
                    $fileUrls[] = $fileUrl;
                }
            }
        }

        // Add image URLs to the product data
        $data['images'] = $fileUrls;

        // Create product document in Firestore
        $docRef = $this->firestore->createDocument('products', $data);

        // Return success response with created data
        return response()->json([
            'message' => 'Data created successfully',
            'data' => $docRef
        ], 201);
    }

    /**
     * Get a specific product by ID
     *
     * @param string $documentId The Firestore document ID of the product
     * @return array Product data
     */
    public function show(string $documentId)
    {
        $data = $this->firestore->readDocumentById('products', $documentId);
        return $data;
    }

    /**
     * Update an existing product
     *
     * @param Request $request The incoming request with updated product data
     * @param string $documentId The Firestore document ID of the product to update
     * @return \Illuminate\Http\JsonResponse Response with updated data
     */
    public function update(Request $request, string $documentId)
    {
        // Validate product data fields
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'images' => 'array|nullable',
            'images.*' => 'file|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Separately validate the replace_images flag
        if ($request->has('replace_images')) {
            $request->validate([
                'replace_images' => 'string|in:true,false,0,1,on,off'
            ]);
        }

        // Get current product document to access existing images
        $document = $this->firestore->readDocumentById('products', $documentId);
        $documentImgs = $document['images'] ?? [];

        // Handle image updates
        $fileUrls = $documentImgs; // Start with existing images
        if ($request->hasFile('images')) {
            // If replace_images flag is true, remove all existing images
            if ($request->boolean('replace_images')) {
                foreach ($documentImgs as $documentImg) {
                    // Delete existing image files from storage
                    $this->fileService->deleteFile($documentImg, 'products', $documentId);
                }
                $fileUrls = []; // Clear the images array
            }

            // Process and upload new images
            foreach ($request->file('images') as $image) {
                $fileUrl = $this->fileService->uploadFile($image, 'products', $documentId);
                if ($fileUrl) {
                    $fileUrls[] = $fileUrl;
                }
            }
        }

        // Add updated image URLs to data for saving
        $data['images'] = $fileUrls;

        // Update the product in Firestore
        $updatedData = $this->firestore->updateDocument('products', $documentId, $data);

        // Return success response
        return response()->json([
            'message' => "Data updated successfully",
            'updated' => $updatedData
        ], 200); // Changed to 200 for successful update (201 is for creation)
    }

    /**
     * Delete a product and its associated images
     *
     * @param string $documentId The Firestore document ID of the product to delete
     * @return \Illuminate\Http\JsonResponse Success message
     */
    public function destroy(string $documentId)
    {
        // Get the product to access its images
        $document = $this->firestore->readDocumentById('products', $documentId);
        $documentImgs = $document['images'] ?? [];

        // Delete all associated image files from storage
        foreach ($documentImgs as $documentImg) {
            $this->fileService->deleteFile($documentImg, 'products', $documentId);
        }

        // Delete the product document from Firestore
        $this->firestore->deleteDocument('products', $documentId);

        // Return success response
        return response()->json([
            'message' => 'Data deleted successfully'
        ], 200);
    }
}