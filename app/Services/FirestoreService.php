<?php

namespace App\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Service for interacting with Google Cloud Firestore database
 * Provides CRUD operations and query functionality for Firestore collections
 */
class FirestoreService
{
    /**
     * The Firestore database instance
     * @var \Google\Cloud\Firestore\FirestoreClient
     */
    protected $firestore;

    /**
     * Initialize the service with Firebase access
     * 
     * @param FirebaseService $firebaseService Service that provides Firebase connections
     */
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firestore = $firebaseService->getFirestore();
    }

    /**
     * Create a new document in a Firestore collection
     * 
     * @param string $collectionName Name of the collection to create document in
     * @param array $data Data to store in the document
     * @return \Google\Cloud\Firestore\DocumentReference Created document reference
     */
    public function createDocument(string $collectionName, array $data)
    {
        // Create new document with auto-generated ID
        $docRef = $this->firestore->collection($collectionName)->newDocument();
        
        // Store the document ID in the document data for easy access
        $data['id'] = $docRef->id();
        
        // Save the document to Firestore
        $docRef->set($data);
        
        return $docRef;
    }

    /**
     * Retrieve all documents from a collection
     * 
     * @param string $collectionName Name of the collection to read documents from
     * @return array Array of documents with their data
     */
    public function readDocuments(string $collectionName)
    {
        // Get all documents in the collection
        $documents = $this->firestore->collection($collectionName)->documents();

        $data = [];

        // Process each document and add to result array
        foreach ($documents as $document) {
            if ($document->exists()) {
                $docData = $document->data();
                // Add the document ID to the data for reference
                $docData['id'] = $document->id();
                $data[] = $docData;
            }
        }

        return $data;
    }

    /**
     * Get a specific document by its ID
     * 
     * @param string $collectionName Name of the collection containing the document
     * @param string $documentId ID of the document to retrieve
     * @return array Document data
     * @throws RouteNotFoundException If document does not exist
     */
    public function readDocumentById(string $collectionName, string $documentId)
    {
        // Get document snapshot
        $snapshot = $this->firestore->collection($collectionName)->document($documentId)->snapshot();

        // If document exists, return its data
        if ($snapshot->exists()) {
            return $snapshot->data();
        } else {
            // Throw exception if document not found
            throw new RouteNotFoundException('No data is found');
        }
    }

    /**
     * Update an existing document with new data
     * 
     * @param string $collectionName Name of the collection containing the document
     * @param string $documentId ID of the document to update
     * @param array $data Data fields to update
     * @return array Updated document data
     * @throws ModelNotFoundException If document does not exist
     */
    public function updateDocument(string $collectionName, string $documentId, array $data)
    {
        // Get a reference to the document
        $docRef = $this->firestore->collection($collectionName)->document($documentId);

        // Check if the document exists before trying to update it
        $snapshot = $docRef->snapshot();
        if (!$snapshot->exists()) {
            throw new ModelNotFoundException("Document with ID {$documentId} not found in {$collectionName}");
        }

        // Format the fields correctly for Firestore update
        $fields = [];
        foreach ($data as $key => $value) {
            // This is the correct format for Firestore's update method
            $fields[] = [
                'path' => $key,
                'value' => $value
            ];
        }

        // Update the document with the formatted data
        $docRef->update($fields);

        // Return the updated document data
        return $docRef->snapshot()->data();
    }

    /**
     * Delete a document from a collection
     * 
     * @param string $collectionName Name of the collection containing the document
     * @param string $documentId ID of the document to delete
     * @return void
     */
    public function deleteDocument(string $collectionName, string $documentId)
    {
        $this->firestore->collection($collectionName)->document($documentId)->delete();
    }

    /**
     * Query documents with specific conditions
     * 
     * @param string $collection Name of the collection to query
     * @param array $conditions Array of condition arrays, each containing [field, operator, value]
     * @return array Matching documents
     */
    public function queryDocuments(string $collection, array $conditions)
    {
        // Start with the base collection reference
        $query = $this->firestore->collection($collection);

        // Apply each condition to the query
        foreach ($conditions as $condition) {
            if (count($condition) === 3) {
                // Each condition should have exactly 3 elements: field, operator, value
                [$field, $operator, $value] = $condition;
                $query = $query->where($field, $operator, $value);
            }
        }

        // Execute the query
        $documents = $query->documents();
        $data = [];

        // Process each result document
        foreach ($documents as $document) {
            if ($document->exists()) {
                // Use document ID as key for easy lookup
                $data[$document->id()] = $document->data();
            }
        }

        return $data;
    }
}