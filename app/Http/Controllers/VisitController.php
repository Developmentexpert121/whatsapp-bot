<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Count;
use App\Models\UserState;
use GuzzleHttp\Client;
use Twilio\Rest\Client as TwilioClient;

class VisitController extends Controller
{
protected $woocommerceUrl = 'https://www.lapepite.co.il/wp-json/wc/v3/';
    protected $consumerKey = 'ck_a5a9de9a4559d8d5bbe5e1fb9b2ab67f2f2a0ac2';
    protected $consumerSecret = 'cs_8d03914496aa2797498f3f95037fb14bdda9f95f';
    protected $twilioAccountSid = 'AC940608513a3f5d1afdc5615bbbe3c286';
    protected $twilioAuthToken = '58645fcb1bef2b85c87b6c9a0b0f609b';
    protected $twilioWhatsAppNumber = 'whatsapp:+14155238886';
    
protected function getLatestDraftProduct()
{
    $response = $this->callWooCommerceApi('products', 'GET', [
        'status' => 'draft',
        'orderby' => 'date',
        'order' => 'desc',
        'per_page' => 1
    ]);
    \Log::info('resp:', ['response' => $response]);

    if (is_array($response) && !empty($response)) {
        return $response[0];
    }

    return null;
}

    protected $userStates = []; // Array to hold user states

    public function handleIncomingMessage(Request $request)
    {
        $body = $request->input('Body');
        $from = $request->input('From');
        $draftProduct = $this->getLatestDraftProduct();
        $mediaUrl = $request->input('MediaUrl0');

        // \Log::info('Media URL:', ['product title' => $body]);
  	$userState = $this->getUserState($from);
        // Greet user
   	 if(empty($userState)){
        // Greet user
        if (stripos($body, 'hi') === 0 || stripos($body, 'hello') === 0 || stripos($body, 'hey') === 0 || stripos($body, 'hlo') === 0) {
            $this->setUserState($from, '');
            return $this->sendMessage($from, "Hello! How can I assist you today?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n");
        }
	}

        \Log::info('userState:', ['userState' => $userState]);
        // Check if the user is in the process of editing
 if ($userState === 'editing') {
    return $this->handleEditOption($body, $from); // Handle the product search/edit process
}
 if ($userState === 'selecting_product') {
    return $this->handleSelectedProduct($body, $from); // Handle the product search/edit process
}
 if ($userState === 'selecting_product_for_delete') {
    return $this->handleSelectedProductDelete($body, $from); // Handle the product search/edit process
}
 if ($userState === 'delete') {
    return $this->handleDelete($body, $from); // Handle the product search/edit process
}
  if ($userState === 'editing_product_selection') {
        return $this->handleFieldSelection($body, $from);
    }
      if ($userState === 'selecting_product_for_delete') {
    }

if ($userState === 'editing_all_price_stock') {
    $field = strtolower($body); // Convert user input to lowercase
    if ($field === 'price') {
        $this->setUserState($from, 'editing_price_all');
        return $this->sendMessage($from, "Please provide the new value for Price.");
    } elseif ($field === 'stock') {
        $this->setUserState($from, 'editing_stock_all');
        return $this->sendMessage($from, "Please provide the new value for Stock.");
    } else {
        return $this->sendMessage($from, "Invalid input. Please enter 'price' or 'stock'.");
    }
}

      if ($userState === 'delete_option') {
        return $this->handleDeleteOption($body, $from);
    }
      if ($userState === 'delete_all_products') {
        return $this->handleAllDeleteOption($body, $from);
    }
         if ($userState === 'delete_yes') {
            return $this->deleteyes($body, $from);
        }
             if ($userState === 'delete_no') {
            return $this->deleteno($body, $from);
        }
        if ($userState === 'editing_name') {
            return $this->updateProductName($body, $from);
        }

        // Check if the user is in the process of editing the price
        if ($userState === 'editing_price') {
            return $this->updateProductPrice($body, $from);
        }
    if ($userState === 'editing_price_all') {
            return $this->updateProductPriceAll($body, $from);
        }
        // Check if the user is in the process of editing the description
        if ($userState === 'editing_description') {
            return $this->updateProductDescription($body, $from);
        }

        // Check if the user is in the process of editing the stock
        if ($userState === 'editing_stock') {
            return $this->updateProductStock($body, $from);
        }
             if ($userState === 'editing_stock_all') {
            return $this->updateProductStockAll($body, $from);
        }
// Check for image URL when waiting for an image
 // Extracting the first media URL
    \Log::info('image:', ['mediaUrl' => $mediaUrl]);
if ($userState === 'waiting_for_image' && $mediaUrl) {
    $this->addProductImage($draftProduct['id'], $mediaUrl); // Add the image to WooCommerce
    $this->setUserState($from, 'adding_description'); // Update state to continue product details
    return $this->sendMessage($from, "Image added. Now, please provide the product description:");
}

if ($userState === 'waiting_for_image' && strcasecmp($body, 'no') === 0) {
    \Log::info('image:', ['I am here in waiting for image but user pressed no']);
    $this->setUserState($from, 'adding_description'); // Update state to continue product details
    return $this->sendMessage($from, "Image not added, please provide the product description:");
}

        if ($draftProduct) {
            $productId = $draftProduct['id'];
            // Title input
             if ($userState === 'adding') {
          if (empty($draftProduct['name']) || $draftProduct['name'] === 'Temporary Title') {
                // Check if the name already exists
    if ($this->doesProductNameExist($body)) {
        return $this->sendMessage($from, "Error: The product name already exists. Please choose a different name.");
    }
                $this->callWooCommerceApi("products/{$productId}", 'PUT', ['name' => $body]);
                $this->setUserState($from, 'waiting_for_image'); // Set state to prompt for image
                return $this->sendMessage($from, "Title set.\n Would you like to add an image? If yes, please upload it now. Or enter No.");
            }
        }
            // Description input
            if (empty($draftProduct['description']) && $userState === 'adding_description') {
                $this->callWooCommerceApi("products/{$productId}", 'PUT', ['description' => $body]);
                $this->setUserState($from, 'adding_sku'); // Update state to continue product details
                return $this->sendMessage($from, "Description added.\n Please provide the product SKU:");
            }

            // SKU input
            if (empty($draftProduct['sku']) && $userState === 'adding_sku') {
                $this->callWooCommerceApi("products/{$productId}", 'PUT', ['sku' => $body]);
                $this->setUserState($from, 'adding_price');
                return $this->sendMessage($from, "SKU added.\n Please provide the price:");
            }

            // Price input
            if (empty($draftProduct['regular_price']) && $userState === 'adding_price') {
                  if (!is_numeric($body)) {
                        return $this->sendMessage($from, "The price must be a numeric value.");
                    }
                $this->callWooCommerceApi("products/{$productId}", 'PUT', ['regular_price' => $body]);
                  $this->setUserState($from, 'addingyesno');
                return $this->sendMessage($from, "Price set.\n Would you like to publish it to your store?\n- Yes\n- No");
            }

            if (stripos($body, 'yes') === 0 && $userState === 'addingyesno') {
                $this->callWooCommerceApi("products/{$productId}", 'PUT', ['status' => 'publish']);
                return $this->sendMessage($from, "Product successfully published. How else can I help you today?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
            } elseif (stripos($body, 'no') === 0 && $userState === 'addingyesno') {
                $this->callWooCommerceApi("products/{$productId}", 'DELETE'); // Call the API to delete the product
                return $this->sendMessage($from, "Product has been deleted. How else can I help you today?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
            }
        }

        // Command Handling with Switch Case
        switch (true) {
            case (stripos($body, 'ADD PRODUCT') === 0 || trim($body) === '1'):
                return $this->initializeProductCreation($from);
            case (stripos($body, 'EDIT PRODUCT') === 0 || trim($body) === '2'):
                return $this->initiateProductEditing($from);
            case (stripos($body, 'DELETE PRODUCT') === 0 || trim($body) === '3'):
                return $this->initiateProductDeletion($from);
            default:
                return $this->sendMessage($from, "Invalid command. Please use:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
        }
    }

protected function doesProductNameExist($productName)
{
    $products = $this->callWooCommerceApi("products", 'GET');
                \Log::info('exists:', ['ests' => $products]);
    if ($products) {
        foreach ($products as $product) {
            if (strcasecmp($product['name'], $productName) === 0) {
                return true; // Name already exists
            }
        }
    }

    return false; // Name is unique
}
public function addProductImage($productId, $mediaUrl)
{
    \Log::info('Attempting to download image from URL', ['mediaUrl' => $mediaUrl]);

    $year = date('Y');
    $month = date('m');
    $uploadDir = public_path("wp-content/uploads/{$year}/{$month}/");

    if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        \Log::error('Failed to create directory', ['uploadDir' => $uploadDir]);
        return;
    }

    \Log::info('Directory created or already exists', ['uploadDir' => $uploadDir]);

    $imageName = 'imagename_' . time() . '.jpg';
    $tempImagePath = $uploadDir . $imageName;

    $ch = curl_init($mediaUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Add headers explicitly with Basic Auth
curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode('AC940608513a3f5d1afdc5615bbbe3c286:58645fcb1bef2b85c87b6c9a0b0f609b'),
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        \Log::error('cURL error: ' . curl_error($ch));
        curl_close($ch);
        return;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        \Log::error('Failed to download image, HTTP Code: ' . $httpCode, ['mediaUrl' => $mediaUrl]);
        return;
    }

    \Log::info('Response size', ['size' => strlen($response)]);

    if (file_put_contents($tempImagePath, $response) === false) {
        \Log::error('Failed to save image to file', ['tempImagePath' => $tempImagePath]);
        return;
    }

    $publicImageUrl = "http://167.99.2.171/wp-content/uploads/{$year}/{$month}/{$imageName}";

    $imageData = [
        'images' => [
            [
                'src' => $publicImageUrl
            ]
        ]
    ];

    \Log::info('API Request Data', ['url' => "products/{$productId}", 'data' => $imageData]);

    $apiResponse = $this->callWooCommerceApi("products/{$productId}", 'PUT', $imageData);

    if ($apiResponse === null) {
        \Log::error('API Request Failed', ['error' => 'No response from API']);
        return;
    }

    \Log::info('API Response', ['response' => $apiResponse]);
}



    private function initiateProductEditing($from)
    {
        // Set the user state to 'editing'
        $this->setUserState($from, 'editing');

        return $this->sendMessage($from, "Please provide the product name or SKU.");
    }

        private function initiateProductDeletion($from)
    {
        $this->setUserState($from, 'delete');

        return $this->sendMessage($from, "Please provide the product name or SKU.");
    }

private function handleFieldSelection($body, $from)
{
    // Retrieve the user's current state
    $userState = $this->getUserState($from);
    \Log::info('userState:', ['userState' => $userState]);

    // Check if the user is in the process of selecting a field to edit
    if ($userState === 'editing_product_selection') {
        // Determine the selected field
        $fieldToEdit = strtolower(trim($body)); // Normalize the input

        // Validate the selected field
        if (in_array($fieldToEdit, ['name', 'price', 'description', 'stock'])) {
            $this->setUserState($from, 'editing_' . $fieldToEdit); // Set user state to the specific field being edited
            return $this->sendMessage($from, "Please provide the new value for $fieldToEdit:");
        } else {
            return $this->sendMessage($from, "Invalid field. Please choose from: Name, Price, Description, Stock.");
        }
    }
}
private function handleDeleteOption($body, $from)
{
    // Normalize and validate input
    $fieldToEdit = strtolower(trim($body));

    if (in_array($fieldToEdit, ['yes', 'no'])) {
        // Set the state and call the corresponding function
        $this->setUserState($from, 'delete_' . $fieldToEdit);

        // Call deleteyes or deleteno based on response
        if ($fieldToEdit === 'yes') {
            return $this->deleteyes($body, $from);
        } else {
            return $this->deleteno($body, $from);
        }
    } else {
        return $this->sendMessage($from, "Invalid Input. Please choose from: Yes or No");
    }
}

private function handleAllDeleteOption($body, $from)
{
    // Check if the user confirms the deletion
    if (strtolower($body) !== 'yes') {
    $this->clearUserState($from);
        return $this->sendMessage($from, "Deletion canceled. No products were deleted.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    }

    // Retrieve the stored product options for the user
    $userState = UserState::where('user_id', $from)->first();
    $productIds = json_decode($userState->product_options, true); // Assuming product IDs are stored in product_ids

    if (empty($productIds)) {
        return $this->sendMessage($from, "No products found to delete.");
    }

    // Delete each product by calling the WooCommerce API
    foreach ($productIds as $productId) {
        $this->callWooCommerceApi("products/{$productId}", 'DELETE');
        \Log::info('Deleted product', ['productId' => $productId]); // Log the deletion
    }

    // Clear user state
    $this->clearUserState($from);

    return $this->sendMessage($from, "All products have been successfully deleted.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
}

private function searchProduct($searchTerm)
{
    // First, search by product name
    $endpointName = "products?search=" . urlencode($searchTerm);
    $responseByName = $this->callWooCommerceApi($endpointName, 'GET');

    // Next, search by SKU if the search term looks like an SKU
    $endpointSku = "products?sku=" . urlencode($searchTerm);
    $responseBySku = $this->callWooCommerceApi($endpointSku, 'GET');

    // Combine results, filtering duplicates
    $response = array_merge($responseByName ?? [], $responseBySku ?? []);
    $uniqueResponse = array_unique($response, SORT_REGULAR);

    \Log::info('Search response', ['response' => $uniqueResponse]);

    // If products found, return them
    if ($uniqueResponse) {
        return $uniqueResponse;
    }

    return []; // Return empty array if no products found or on error
}



    private function getCurrentEditingProductId($from)
{
    // Fetch the user state from the database
    $userState = UserState::where('user_id', $from)->first();
        \Log::info('userState2:', ['userState2' => $userState]);

    // Assuming you have a way to store or retrieve the product ID related to the editing state.
    if ($userState && $userState->state === 'editing_price') {
        // Return the product ID (you may need to adjust this part based on your logic)
        return $userState->current_product_id; // Make sure to store this ID in the user state when you start editing
    }

    // Handle the case where there's no valid state or product ID
    return null; // Or throw an exception, or handle as needed
}

private function handleEditOption($body, $from)
{
    // Check if the body is a valid product name or SKU
    $products = $this->searchProduct($body);
    \Log::info('issg', ['issg' => json_encode($products)]);

    if (count($products) > 1) {
        // Multiple products found, prompt the user to select one or all
        $productList = implode("\n", array_map(function($product) {
            return "{$product['id']}: {$product['name']} (SKU: {$product['sku']})";
        }, $products));

        $responseMessage = "Multiple products found:\n$productList\nPlease reply with the Product ID you want to edit or type 'all' to edit all products at once.";

        $this->setUserState($from, 'selecting_product'); // Set user state to selecting product
        $this->storeProductOptions($from, $products); // Save products for reference in user selection
        return $this->sendMessage($from, $responseMessage);

    } elseif (count($products) === 1) {
        // Only one product found, proceed with editing
        $productId = $products[0]['id'];
        UserState::updateOrCreate(
            ['user_id' => $from],
            ['current_product_id' => $productId] // Store the current product ID
        );

        // Prepare response
        $responseMessage = "Product found: {$products[0]['name']}\nPlease select a specific field to edit:\n- Name\n- Price\n- Description\n- Stock";
        $this->setUserState($from, 'editing_product_selection'); // Set user state to editing product selection
        return $this->sendMessage($from, $responseMessage);
    } else {
        // No products found
        return $this->sendMessage($from, "No products found. Please provide a different name or SKU.");
    }
}

// Helper function to store the products temporarily for user selection
private function storeProductOptions($userId, $products)
{
    // Store product options in a temporary table or session data
    UserState::updateOrCreate(
        ['user_id' => $userId],
        ['product_options' => json_encode($products)]
    );
}
private function handleSelectedProduct($body, $from)
{
    // Retrieve the stored product options for the user
    $userState = UserState::where('user_id', $from)->first();
    $products = json_decode($userState->product_options, true);
    \Log::info('opt', ['opt' => $products]);
    // Check if the user input is "all"
    if (strtolower($body) === 'all') {
        // Set state to update price and stock for all products
        $this->setUserState($from, 'editing_all_price_stock');
        return $this->sendMessage($from, "You chose to edit all products. Please choose what to edit\n-Price\n-Stock");
    }

    // Check if the user input matches any product ID in the stored options
    $selectedProduct = collect($products)->firstWhere('id', $body);
    if ($selectedProduct) {
        // Store the selected product ID for further edits
        UserState::updateOrCreate(
            ['user_id' => $from],
            ['current_product_id' => $selectedProduct['id']]
        );

        // Prompt the user to choose a field to edit
        $responseMessage = "Product selected: {$selectedProduct['name']}\nPlease select a specific field to edit:\n- Name\n- Price\n- Description\n- Stock";
        $this->setUserState($from, 'editing_product_selection');
        return $this->sendMessage($from, $responseMessage);
    }

    // If input is invalid, prompt the user again
    return $this->sendMessage($from, "Invalid selection. Please provide a valid Product ID or type 'all' to edit all products.");
}

private function handleSelectedProductDelete($body, $from)
{
    // Retrieve the stored product options for the user
    $userState = UserState::where('user_id', $from)->first();
    $products = json_decode($userState->product_options, true);
    \Log::info('opt', ['opt' => $products]);
    // Check if the user input is "all"
    if (strtolower($body) === 'all') {
        // Set state to update price and stock for all products
        $this->setUserState($from, 'delete_all_products');
        return $this->sendMessage($from, "You chose to delete all products. Please confirm you want to delete all\n-Yes\n-No");
    }

    // Check if the user input matches any product ID in the stored options
    $selectedProduct = collect($products)->firstWhere('id', $body);
    if ($selectedProduct) {
        // Store the selected product ID for further edits
        UserState::updateOrCreate(
            ['user_id' => $from],
            ['current_product_id' => $selectedProduct['id']]
        );

        // Prompt the user to choose a field to edit
        $responseMessage = "Product selected: {$selectedProduct['name']}\nAre you sure you want to delete?\n-Yes\n-No";
        $this->setUserState($from, 'delete_option');
        return $this->sendMessage($from, $responseMessage);
    }

    // If input is invalid, prompt the user again
    return $this->sendMessage($from, "Invalid selection. Please provide a valid Product ID or type 'all' to edit all products.");
}

private function handleDelete($body, $from)
{
    // Check if the body is a valid product name or SKU
    $products = $this->searchProduct($body);
    \Log::info('issg', ['issg' => $products]);

    if (count($products) > 1) {
        // Multiple products found, prompt the user to select one
        $productList = implode("\n", array_map(function($product) {
            return "{$product['id']}: {$product['name']}";
        }, $products));

             $productIds = array_column($products, 'id');
        UserState::updateOrCreate(
            ['user_id' => $from],
            ['product_options' => json_encode($productIds)] // Store the product IDs as a JSON string
        );

        $responseMessage = "Multiple products found:\n$productList\nPlease reply with the Product ID you want to delete. Or type 'all' to delete all. ";
        $this->setUserState($from, 'selecting_product_for_delete'); // Set user state to selecting product for deletion
        return $this->sendMessage($from, $responseMessage);
    } elseif (count($products) === 1) {
        // Only one product found, proceed to delete
        $productId = $products[0]['id'];
        UserState::updateOrCreate(
            ['user_id' => $from],
            [
                'current_product_id' => $productId // Store the current product ID
            ]
        );

        $responseMessage = "Product found: {$products[0]['name']}\nWould you like to delete this product?\n- Yes\n- No\n";
        $this->setUserState($from, 'delete_option'); // Set user state to confirm deletion
        return $this->sendMessage($from, $responseMessage);
    } else {
        // No products found
        return $this->sendMessage($from, "No products found. Please provide a different name or SKU.");
    }
}

 private function editProductPrice($from, $productId)
{
    // Set user state for price editing and store the product ID
    $this->setUserState($from, 'editing_price', $productId);

    return $this->sendMessage($from, "Please provide the new product price:");
}

    private function updateProductPrice($newPrice, $from)
    {
        if (!is_numeric($newPrice)) {
            return $this->sendMessage($from, "The price must be a numeric value.");
        }
        // Retrieve the product ID
        $productId = UserState::where('user_id', $from)->pluck('current_product_id')->first();
                \Log::info('productId:', ['productId' => $productId]);

        // Call the WooCommerce API to update the product price
        $this->callWooCommerceApi("products/{$productId}", 'PUT', ['regular_price' => $newPrice]);

        // Clear user state
        $this->clearUserState($from);

        return $this->sendMessage($from, "Product price updated successfully.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    }

   private function updateProductPriceAll($newPrice, $from)
{
    if (!is_numeric($newPrice)) {
        return $this->sendMessage($from, "The price must be a numeric value.");
    }

    // Retrieve the stored product options for the user
    $productOptions = UserState::where('user_id', $from)->pluck('product_options')->first();

    // Decode the JSON product options to get the list of product IDs
    $productIds = json_decode($productOptions, true);

    if (is_array($productIds)) {
        foreach ($productIds as $product) {
            $productId = $product['id'];
            \Log::info('Updating price for productId:', ['productId' => $productId]);

            // Call the WooCommerce API to update each product's price
            $this->callWooCommerceApi("products/{$productId}", 'PUT', ['regular_price' => $newPrice]);
        }

        // Clear user state after updating all products
        $this->clearUserState($from);

        return $this->sendMessage($from, "Product prices updated successfully for all selected products.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    } else {
        return $this->sendMessage($from, "Error retrieving product options. Please try again.");
    }
}


      private function updateProductName($newPrice, $from)
    {
        // Retrieve the product ID
        $productId = UserState::where('user_id', $from)->pluck('current_product_id')->first();
                \Log::info('productId:', ['productId' => $productId]);

    // Check if the new name already exists
    if ($this->doesProductNameExist($newPrice)) {
                \Log::info('trueeeeee');
        return $this->sendMessage($from, "Error: The product name already exists. Please choose a different name.");
    }
        // Call the WooCommerce API to update the product price
        $this->callWooCommerceApi("products/{$productId}", 'PUT', ['name' => $newPrice]);

        // Clear user state
        $this->clearUserState($from);

        return $this->sendMessage($from, "Product name updated successfully.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    }
     private function updateProductDescription($newPrice, $from)
    {
        // Retrieve the product ID
        $productId = UserState::where('user_id', $from)->pluck('current_product_id')->first();
                \Log::info('productId:', ['productId' => $productId]);

        // Call the WooCommerce API to update the product price
        $this->callWooCommerceApi("products/{$productId}", 'PUT', ['description' => $newPrice]);

        // Clear user state
        $this->clearUserState($from);

        return $this->sendMessage($from, "Product description updated successfully.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    }
      private function updateProductStock($newPrice, $from)
    {
           if (!is_numeric($newPrice)) {
        return $this->sendMessage($from, "The stock must be a numeric value.");
             }
        // Retrieve the product ID
        $productId = UserState::where('user_id', $from)->pluck('current_product_id')->first();
                \Log::info('productId:', ['productId' => $productId]);

        // Call the WooCommerce API to update the product price
        $this->callWooCommerceApi("products/{$productId}", 'PUT', ['stock_quantity' => $newPrice,'manage_stock' => true]);

        // Clear user state
        $this->clearUserState($from);

        return $this->sendMessage($from, "Product stock updated successfully.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    }
 private function updateProductStockAll($newPrice, $from)
{
    if (!is_numeric($newPrice)) {
        return $this->sendMessage($from, "The stock must be a numeric value.");
    }

    // Retrieve the stored product options for the user
    $productOptions = UserState::where('user_id', $from)->pluck('product_options')->first();

    // Decode the JSON product options to get the list of product IDs
    $productIds = json_decode($productOptions, true);

    if (is_array($productIds)) {
        foreach ($productIds as $product) {
            $productId = $product['id'];
            \Log::info('Updating stock for productId:', ['productId' => $productId]);

            // Call the WooCommerce API to update each product's stock
            $this->callWooCommerceApi("products/{$productId}", 'PUT', ['stock_quantity' => $newPrice,'manage_stock' => true]);
        }

        // Clear user state after updating all products
        $this->clearUserState($from);

        return $this->sendMessage($from, "Product stock updated successfully for all selected products.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    } else {
        return $this->sendMessage($from, "Error retrieving product options. Please try again.");
    }
}

private function setUserState($from, $state, $productId = null)
{

    UserState::updateOrCreate(
        ['user_id' => $from],
        [
            'state' => $state// Store the current product ID if provided
        ]
    );
}
private function getUserState($from)
{
    $userState = UserState::where('user_id', $from)->first();

    return $userState ? $userState->state : null;
}

private function clearUserState($from)
{
    UserState::where('user_id', $from)->delete();
}


protected function fetchImage($url)
{
    try {
        $client = new Client();
        $response = $client->get($url);
        if ($response->getStatusCode() === 200) {
            return (string)$response->getBody(); // Return image data or URL
        } else {
            \Log::error('Image fetch failed with status', ['status' => $response->getStatusCode()]);
            return null;
        }
    } catch (\Exception $e) {
        \Log::error('Image fetch exception', ['error' => $e->getMessage()]);
        return null;
    }
}

protected function createProduct($details, $from)
{
    return $this->callWooCommerceApi('products', 'POST', $details);
}

protected function listProducts($from)
{
    $response = $this->callWooCommerceApi('products?per_page=20', 'GET'); // Limits to 20 products

    if ($response) {
        $characterLimit = 1500; // Safe limit to prevent exceeding Twilio's character limit
        $publishedProductsCount = 0; // Counter to track published products

        foreach ($response as $product) {
            // Check if the product status is 'publish'
            if (stripos($product['status'], 'publish') === 0) {
                // Retrieve product details
                $productDetails = "ID: " . $product['id'] . "\n" .
                                  "Name: " . $product['name'] . "\n" .
                                  "Price: $" . $product['price'] . "\n" .
                                  "Description: " . strip_tags($product['description']) . "\n" .
                                  "Status: " . $product['status'] . "\n" .
                                  "Link: " . $product['permalink'] . "\n";

                // Retrieve image URL
                $imageUrl = $product['images'][0]['src'] ?? null;

                // Check if product details are within the character limit
                if (strlen($productDetails) > $characterLimit) {
                    $productDetails = substr($productDetails, 0, $characterLimit - 3) . "...";
                }

                // Send image and caption if available, otherwise just the text
                if ($imageUrl) {
                    $this->sendMediaMessage($from, $imageUrl, $productDetails);
                } else {
                    $this->sendMessage($from, $productDetails);
                }

                $publishedProductsCount++; // Increment the count for published products
            }
        }

        // If no published products were found, send a message
        if ($publishedProductsCount === 0) {
            return $this->sendMessage($from, "No published products found.");
        }

        return true;
    } else {
        return $this->sendMessage($from, "Failed to retrieve product list.");
    }
}


protected function sendMediaMessage($to, $mediaUrl, $caption)
{
    try {
        $twilio = new TwilioClient($this->twilioAccountSid, $this->twilioAuthToken);
        $twilio->messages->create(
            $to,
            [
                'from' => $this->twilioWhatsAppNumber,
                'mediaUrl' => [$mediaUrl],
                'body' => $caption
            ]
        );
        \Log::info("Media message sent to {$to} with caption: {$caption}");
    } catch (\Exception $e) {
        \Log::error("Twilio API Error while sending media: " . $e->getMessage());
    }
}
///////////////////////////////////////////////////////////////////////////////////
// add product
public function initializeProductCreation($from)
{
      $this->setUserState($from, 'adding');
    // Create a draft product in WooCommerce and return its ID
    $productData = [
        'name' => 'Temporary Title',
        'status' => 'draft'
    ];

    $product = $this->callWooCommerceApi('products', 'POST', $productData);

    if ($product && isset($product['id'])) {
        return $this->sendMessage($from, "Please provide the product title:");
    } else {
        return $this->sendMessage($from, "An error occurred while initializing the product creation. Please try again.");
    }
}

/////////////////////////////////////////////////////////////////////////


    private function deleteyes($newPrice, $from)
    {
        // Retrieve the product ID
        $productId = UserState::where('user_id', $from)->pluck('current_product_id')->first();

        // Call the WooCommerce API to update the product price
        $this->callWooCommerceApi("products/{$productId}", 'DELETE');

        // Clear user state
        $this->clearUserState($from);

        return $this->sendMessage($from, "Product was deleted successfully.\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    }
       private function deleteno($newPrice, $from)
    {
        // Clear user state
        $this->clearUserState($from);

        return $this->sendMessage($from, "Deletion Cancelled!\nNeed help with anything else?\nChoose an action:\n1. ADD PRODUCT\n2. EDIT PRODUCT\n3. DELETE PRODUCT\n4. LIST PRODUCTS\n5. ABORT");
    }


    protected function callWooCommerceApi($endpoint, $method, $data = [])
    {
        $client = new Client();
        try {
            $response = $client->request($method, $this->woocommerceUrl . $endpoint, [
                'auth' => [$this->consumerKey, $this->consumerSecret],
                'json' => $data,
            ]);

            // Log the response
            \Log::info('WooCommerce API Response', ['response' => json_decode($response->getBody(), true)]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            \Log::error('API Request Failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

  protected function sendMessage($to, $message)
{
    // Ensure the Twilio client is set up correctly
    $client = new Client();

    // Format the 'To' number
    $toFormatted = 'whatsapp:' . ltrim($to, 'whatsapp:');

    try {
        // Prepare the payload for Twilio API
        $payload = [
            'To' => $toFormatted,
            'From' => $this->twilioWhatsAppNumber,
            'Body' => $message, // Ensure this is not empty
        ];

        // Log the payload for debugging
        \Log::info('Twilio Payload: ', $payload);

        // Send the POST request to Twilio API
        $response = $client->post("https://api.twilio.com/2010-04-01/Accounts/{$this->twilioAccountSid}/Messages.json", [
            'auth' => [$this->twilioAccountSid, $this->twilioAuthToken],
            'form_params' => $payload,
        ]);

        // Handle the response
        $responseBody = json_decode($response->getBody(), true);
        return $responseBody;

    } catch (\Exception $e) {
        // Log the error message for debugging
        \Log::error('Twilio API Error: ' . $e->getMessage());
        return null;
    }
}

}
