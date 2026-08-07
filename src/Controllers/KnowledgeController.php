<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\Services\VectorService;
use App\Services\LlmService;

class KnowledgeController {
    
    private function render(Response $response, string $template, array $data = []): Response {
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$template}.php";
        $content = ob_get_clean();
        
        ob_start();
        include __DIR__ . "/../views/layout.php";
        $html = ob_get_clean();
        
        $response->getBody()->write($html);
        return $response;
    }

    public function listText(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM knowledge WHERE type = 'text' ORDER BY id DESC");
        $items = $stmt->fetchAll();

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'text_list', [
            'items' => $items,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function saveText(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $data = $request->getParsedBody();
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $id = $data['id'] ?? null;

        if (empty($content)) {
            $_SESSION['error'] = 'Content cannot be empty.';
            return $response->withHeader('Location', BASE_URL . '/admin/text')->withStatus(302);
        }

        try {
            $db = Database::getConnection();
            
            if ($id) {
                $stmt = $db->prepare("UPDATE knowledge SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO knowledge (type, title, content) VALUES ('text', ?, ?)");
                $stmt->execute([$title, $content]);
                $id = $db->lastInsertId();
            }

            $_SESSION['success'] = 'Information saved successfully.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error saving information: ' . $e->getMessage();
        }

        return $response->withHeader('Location', BASE_URL . '/admin/text')->withStatus(302);
    }

    public function deleteText(Request $request, Response $response, array $args): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $id = $args['id'] ?? null;
        if ($id) {
            try {
                $db = Database::getConnection();
                $stmt = $db->prepare("DELETE FROM knowledge WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = 'Information deleted successfully.';
            } catch (\Exception $e) {
                $_SESSION['error'] = 'Error deleting information: ' . $e->getMessage();
            }
        }
        
        return $response->withHeader('Location', BASE_URL . '/admin/text')->withStatus(302);
    }

    public function listQa(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM knowledge WHERE type = 'qa' ORDER BY id DESC");
        $items = $stmt->fetchAll();

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'qa_list', [
            'items' => $items,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function saveQa(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $data = $request->getParsedBody();
        $question = trim($data['content'] ?? '');
        $answer = trim($data['answer'] ?? '');
        $id = $data['id'] ?? null;

        if (empty($question) || empty($answer)) {
            $_SESSION['error'] = 'Question and Answer cannot be empty.';
            return $response->withHeader('Location', BASE_URL . '/admin/qa')->withStatus(302);
        }

        try {
            $db = Database::getConnection();
            $llm = new LlmService();
            $vectorService = new VectorService();
            
            $fullContext = "Q: {$question} A: {$answer}";
            $chunks = [$question]; // Always include the question as a chunk
            
            // Chunk the answer by paragraphs
            $paragraphs = preg_split('/\n\n+/', $answer);
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if (!empty($p)) {
                    $chunks[] = $p;
                }
            }

            $qdrantIds = [];
            
            // Delete old vectors if updating
            if ($id) {
                $stmt = $db->prepare("SELECT qdrant_id FROM knowledge WHERE id = ?");
                $stmt->execute([$id]);
                $oldQdrantIds = $stmt->fetchColumn();
                if ($oldQdrantIds) {
                    $oldIdsArray = json_decode($oldQdrantIds, true);
                    if (is_array($oldIdsArray)) {
                        foreach ($oldIdsArray as $oldId) {
                            $vectorService->delete($oldId);
                        }
                    } else {
                        // Backwards compatibility for single UUID
                        $vectorService->delete($oldQdrantIds);
                    }
                }
            }
            
            foreach ($chunks as $chunk) {
                $vector = $llm->embed($chunk);
                $sparseVector = $llm->generateSparseVector($chunk);
                $qdrantId = VectorService::generateUuid();
                
                $vectorService->upsert($qdrantId, $vector, [
                    'type' => 'qa',
                    'internal_id' => $id ?? 'temp', // We will update this later if it's an insert
                    'search_content' => $fullContext // The parent full context
                ], $sparseVector);
                
                $qdrantIds[] = $qdrantId;
            }

            $qdrantIdsJson = json_encode($qdrantIds);

            if ($id) {
                $stmt = $db->prepare("UPDATE knowledge SET content = ?, answer = ?, qdrant_id = ? WHERE id = ?");
                $stmt->execute([$question, $answer, $qdrantIdsJson, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO knowledge (type, content, answer, qdrant_id) VALUES ('qa', ?, ?, ?)");
                $stmt->execute([$question, $answer, $qdrantIdsJson]);
                $id = $db->lastInsertId();
                
                // Update the internal_id in Qdrant for newly inserted record
                foreach ($qdrantIds as $idx => $chunkQdrantId) {
                    $chunk = $chunks[$idx];
                    $vector = $llm->embed($chunk); // Re-embedding is wasteful, but let's just do it or we can avoid it.
                    $sparseVector = $llm->generateSparseVector($chunk);
                    $vectorService->upsert($chunkQdrantId, $vector, [
                        'type' => 'qa',
                        'internal_id' => $id,
                        'search_content' => $fullContext
                    ], $sparseVector);
                }
            }

            $_SESSION['success'] = 'Q&A saved successfully.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error saving Q&A: ' . $e->getMessage();
        }

        return $response->withHeader('Location', BASE_URL . '/admin/qa')->withStatus(302);
    }

    public function deleteQa(Request $request, Response $response, array $args): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $id = $args['id'] ?? null;
        if ($id) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT qdrant_id FROM knowledge WHERE id = ?");
            $stmt->execute([$id]);
            $qdrantId = $stmt->fetchColumn();

            if ($qdrantId) {
                $vectorService = new VectorService();
                $ids = json_decode($qdrantId, true);
                if (is_array($ids)) {
                    foreach ($ids as $cid) {
                        $vectorService->delete($cid);
                    }
                } else {
                    $vectorService->delete($qdrantId);
                }
            }

            $stmt = $db->prepare("DELETE FROM knowledge WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Q&A deleted successfully.';
        }
        
        return $response->withHeader('Location', BASE_URL . '/admin/qa')->withStatus(302);
    }

    public function listProducts(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM products ORDER BY updated_at DESC");
        $products = $stmt->fetchAll();

        $stmtUrl = $db->query("SELECT value FROM settings WHERE key = 'product_feed_url'");
        $feedUrl = $stmtUrl->fetchColumn();

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'products', [
            'products' => $products,
            'feedUrl' => $feedUrl,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function prepareSync(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withStatus(403);
        
        try {
            $syncService = new \App\Services\SyncService();
            $result = $syncService::prepareSync();
            $response->getBody()->write(json_encode(['success' => true, 'data' => $result]));
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function processSyncChunk(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withStatus(403);
        
        try {
            $syncService = new \App\Services\SyncService();
            // Process 15 items per chunk by default to balance speed and timeout risk
            $result = $syncService::processSyncChunk(15);
            $response->getBody()->write(json_encode(['success' => true, 'data' => $result]));
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function finalizeSync(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withStatus(403);
        
        try {
            $syncService = new \App\Services\SyncService();
            $syncService::finalizeSync();
            $_SESSION['success'] = 'Products synchronized successfully.';
            $response->getBody()->write(json_encode(['success' => true]));
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function deleteAllProducts(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        try {
            $db = Database::getConnection();
            $vectorService = new VectorService();
            
            $stmt = $db->query("SELECT qdrant_id FROM products");
            while ($row = $stmt->fetch()) {
                if (!empty($row['qdrant_id'])) {
                    $vectorService->delete($row['qdrant_id']);
                }
            }
            
            $db->exec("DELETE FROM products");
            
            $_SESSION['success'] = 'All products deleted successfully.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete products: ' . $e->getMessage();
        }

        return $response->withHeader('Location', BASE_URL . '/admin/products')->withStatus(302);
    }

    public function setProductImage(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withStatus(403);
        
        try {
            $data = $request->getParsedBody();
            $productId = $data['product_id'] ?? '';
            $imageUrl = $data['image_url'] ?? '';

            if (empty($productId) || empty($imageUrl)) {
                throw new \Exception('Product ID and Image URL are required.');
            }

            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE products SET image_url = ?, updated_at = CURRENT_TIMESTAMP WHERE product_id = ?");
            $stmt->execute([$imageUrl, $productId]);
            
            // Re-embed the product since image has changed
            $stmt = $db->prepare("SELECT hash, qdrant_id FROM products WHERE product_id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product && !empty($product['qdrant_id'])) {
                $vectorService = new \App\Services\VectorService();
                $qdrantPoint = $vectorService->getPoint($product['qdrant_id']);
                
                if ($qdrantPoint && !empty($qdrantPoint['payload']['search_content'])) {
                    $searchContent = $qdrantPoint['payload']['search_content'];
                    
                    // Download and compress image
                    $imgData = @file_get_contents($imageUrl);
                    if ($imgData) {
                        $im = @imagecreatefromstring($imgData);
                        if ($im !== false) {
                            $width = imagesx($im);
                            $height = imagesy($im);
                            $maxSize = 512;
                            if ($width > $maxSize || $height > $maxSize) {
                                $ratio = min($maxSize / $width, $maxSize / $height);
                                $newWidth = (int)($width * $ratio);
                                $newHeight = (int)($height * $ratio);
                                $newIm = imagecreatetruecolor($newWidth, $newHeight);
                                
                                // Preserve transparency for PNGs
                                imagealphablending($newIm, false);
                                imagesavealpha($newIm, true);
                                $transparent = imagecolorallocatealpha($newIm, 255, 255, 255, 127);
                                imagefilledrectangle($newIm, 0, 0, $newWidth, $newHeight, $transparent);
                                
                                imagecopyresampled($newIm, $im, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                imagedestroy($im);
                                $im = $newIm;
                            }
                            
                            // Create white background for transparent images when saving as JPEG
                            $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
                            $white = imagecolorallocate($bg, 255, 255, 255);
                            imagefill($bg, 0, 0, $white);
                            imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
                            
                            ob_start();
                            imagejpeg($bg, null, 80);
                            $compressedImgData = ob_get_clean();
                            imagedestroy($im);
                            imagedestroy($bg);
                            
                            $imageBase64 = base64_encode($compressedImgData);
                            
                            $llm = new LlmService();
                            // Embed both text and image
                            $vector = $llm->embed($searchContent, $imageBase64, 'image/jpeg');
                            $sparseVector = $llm->generateSparseVector($searchContent);
                            
                            // Upsert back to Qdrant
                            $payload = $qdrantPoint['payload'];
                            $payload['thumbnail_url'] = $imageUrl; 
                            $vectorService->upsert($product['qdrant_id'], $vector, $payload, $sparseVector);
                        }
                    }
                }
            }

            $response->getBody()->write(json_encode(['success' => true]));
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}
