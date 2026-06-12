<?php

namespace Cypher\Compiler\SourceGenerator;

class ApplicationInterpreter
{
    private const APP_PATTERNS = [
        'real estate' => 'RealEstateApp',
        'ecommerce' => 'ECommerceApp',
        'blog' => 'BlogApp',
        'saas' => 'SaaSApp',
        'crm' => 'CRMApp',
        'social' => 'SocialApp',
        'chat' => 'ChatApp',
        'todo' => 'TodoApp',
        'dashboard' => 'DashboardApp',
        'api' => 'ApiApp',
        'portfolio' => 'PortfolioApp',
        'landing' => 'LandingPage',
        'admin' => 'AdminPanel',
    ];

    public function interpret(string $name, string $description, array $options = []): array
    {
        $desc = strtolower($description);
        $appType = $this->detectAppType($desc);

        $spec = [
            'name' => $name,
            'type' => $appType,
            'models' => [],
            'pages' => [],
            'apis' => [],
            'agents' => [],
            'workflows' => [],
            'components' => [],
        ];

        $spec = $this->applyAppPattern($spec, $desc);

        if (preg_match('/\b(properties|listings|houses|real\s*estate)\b/', $desc)) {
            $spec = $this->enrichRealEstate($spec);
        }
        if (preg_match('/\b(products|catalog|shop|store|cart|checkout)\b/', $desc)) {
            $spec = $this->enrichECommerce($spec);
        }
        if (preg_match('/\b(users|auth|login|register|admin)\b/', $desc)) {
            $spec = $this->enrichAuth($spec);
        }
        if (preg_match('/\b(blog|posts|articles|news)\b/', $desc)) {
            $spec = $this->enrichBlog($spec);
        }
        if (preg_match('/\b(pdf|download|documents|files)\b/', $desc)) {
            $spec = $this->enrichDocuments($spec);
        }
        if (preg_match('/\b(agent|chat|ai|assistant|support)\b/', $desc)) {
            $spec = $this->enrichAgent($spec, $desc);
        }
        if (preg_match('/\b(payment|billing|subscription)\b/', $desc)) {
            $spec = $this->enrichPayments($spec);
        }
        if (preg_match('/\b(email|notification|notify|alert)\b/', $desc)) {
            $spec = $this->enrichNotifications($spec);
        }
        if (preg_match('/\b(search|filter|find)\b/', $desc)) {
            $spec = $this->enrichSearch($spec, $desc);
        }
        if (preg_match('/\b(review|rating|comment|feedback)\b/', $desc)) {
            $spec = $this->enrichReviews($spec);
        }
        if (preg_match('/\b(analytics|report|insight|metric)\b/', $desc)) {
            $spec = $this->enrichAnalytics($spec);
        }
        if (preg_match('/\b(workflow|automation|pipeline|process)\b/', $desc)) {
            $spec = $this->enrichWorkflows($spec, $desc);
        }
        if (preg_match('/\b(contact|inquiry|lead|form)\b/', $desc)) {
            $spec = $this->enrichContacts($spec);
        }
        if (preg_match('/\b(api|rest|integration|webhook)\b/', $desc)) {
            $spec = $this->enrichExternalApis($spec, $desc);
        }

        return $spec;
    }

    private function detectAppType(string $desc): string
    {
        foreach (self::APP_PATTERNS as $keyword => $type) {
            if (str_contains($desc, $keyword)) {
                return $type;
            }
        }
        return 'CustomApp';
    }

    private function applyAppPattern(array $spec, string $desc): array
    {
        $spec['models'][] = ['name' => 'User', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'email', 'type' => 'string', 'attributes' => ['unique']],
            ['name' => 'password', 'type' => 'string'],
        ]];

        $spec['pages'][] = [
            'name' => 'Home',
            'content' => [
                ['type' => 'var', 'name' => 'title', 'value' => $spec['name']],
                ['type' => 'say', 'value' => "<h1 class='text-4xl font-bold'>{title}</h1>"],
            ],
        ];

        return $spec;
    }

    private function enrichRealEstate(array $spec): array
    {
        $spec['models'][] = ['name' => 'Property', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'description', 'type' => 'text'],
            ['name' => 'price', 'type' => 'float'],
            ['name' => 'bedrooms', 'type' => 'int'],
            ['name' => 'bathrooms', 'type' => 'int'],
            ['name' => 'square_feet', 'type' => 'int'],
            ['name' => 'address', 'type' => 'string'],
            ['name' => 'city', 'type' => 'string'],
            ['name' => 'state', 'type' => 'string'],
            ['name' => 'zip_code', 'type' => 'string'],
            ['name' => 'property_type', 'type' => 'string'],
            ['name' => 'status', 'type' => 'string'],
            ['name' => 'image_url', 'type' => 'string'],
            ['name' => 'user_id', 'type' => 'int'],
        ], 'relationships' => [
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];

        $spec['models'][] = ['name' => 'PropertyInquiry', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'email', 'type' => 'string'],
            ['name' => 'phone', 'type' => 'string'],
            ['name' => 'message', 'type' => 'text'],
            ['name' => 'property_id', 'type' => 'int'],
            ['name' => 'user_id', 'type' => 'int'],
        ], 'relationships' => [
            ['name' => 'property', 'type' => 'belongsTo', 'target' => 'Property'],
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];

        $spec['pages'][] = [
            'name' => 'Properties',
            'content' => [
                ['type' => 'var', 'name' => 'title', 'value' => 'Browse Properties'],
                ['type' => 'say', 'value' => "<h1 class='text-3xl font-bold'>{title}</h1>"],
                ['type' => 'say', 'value' => "<div class='grid grid-cols-1 md:grid-cols-3 gap-6'>"],
                ['type' => 'say', 'value' => "{/* Property cards rendered here */}"],
                ['type' => 'say', 'value' => "</div>"],
            ],
        ];

        $spec['pages'][] = [
            'name' => 'PropertyDetail',
            'content' => [
                ['type' => 'say', 'value' => "<h1 class='text-3xl font-bold'>{property.title}</h1>"],
            ],
        ];

        $spec['apis'][] = ['method' => 'GET', 'path' => '/api/properties', 'body' => [
            ['type' => 'return', 'value' => 'Property::all()'],
        ]];
        $spec['apis'][] = ['method' => 'GET', 'path' => '/api/properties/{id}', 'body' => [
            ['type' => 'return', 'value' => '{status: "found"}'],
        ]];
        $spec['apis'][] = ['method' => 'POST', 'path' => '/api/inquiries', 'body' => [
            ['type' => 'return', 'value' => '{status: "created"}'],
        ]];

        $spec['agents'][] = [
            'name' => 'PropertyAssistant',
            'model' => 'gpt4',
            'prompt' => 'You are a helpful real estate assistant.',
            'tasks' => [
                ['name' => 'answerQuestion', 'params' => 'question', 'body' => [
                    'response = ask(question)',
                    'return response',
                ]],
            ],
        ];

        $spec['workflows'][] = [
            'name' => 'InquiryWorkflow',
            'steps' => [
                ['name' => 'Receive Inquiry', 'body' => ['say "New inquiry received"']],
                ['name' => 'Notify Agent', 'body' => ['say "Notifying assigned agent"']],
                ['name' => 'Follow Up', 'body' => ['say "Scheduling follow-up"']],
            ],
        ];

        return $spec;
    }

    private function enrichECommerce(array $spec): array
    {
        $spec['models'][] = ['name' => 'Product', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'description', 'type' => 'text'],
            ['name' => 'price', 'type' => 'float'],
            ['name' => 'sku', 'type' => 'string'],
            ['name' => 'stock', 'type' => 'int'],
            ['name' => 'category_id', 'type' => 'int'],
            ['name' => 'image_url', 'type' => 'string'],
        ], 'relationships' => [
            ['name' => 'category', 'type' => 'belongsTo', 'target' => 'Category'],
        ]];
        $spec['models'][] = ['name' => 'Category', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'slug', 'type' => 'string', 'attributes' => ['unique']],
        ], 'relationships' => [
            ['name' => 'products', 'type' => 'hasMany', 'target' => 'Product'],
        ]];
        $spec['models'][] = ['name' => 'Order', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'user_id', 'type' => 'int'],
            ['name' => 'total', 'type' => 'float'],
            ['name' => 'status', 'type' => 'string'],
        ], 'relationships' => [
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];
        $spec['pages'][] = ['name' => 'Products', 'content' => [
            ['type' => 'var', 'name' => 'title', 'value' => 'Our Products'],
            ['type' => 'say', 'value' => "<h1 class='text-3xl font-bold'>{title}</h1>"],
        ]];
        $spec['pages'][] = ['name' => 'Cart', 'content' => [
            ['type' => 'var', 'name' => 'title', 'value' => 'Shopping Cart'],
            ['type' => 'say', 'value' => "<h1 class='text-3xl font-bold'>{title}</h1>"],
        ]];
        $spec['apis'][] = ['method' => 'GET', 'path' => '/api/products', 'body' => [
            ['type' => 'return', 'value' => 'Product::all()'],
        ]];
        $spec['apis'][] = ['method' => 'POST', 'path' => '/api/orders', 'body' => [
            ['type' => 'return', 'value' => '{status: "created"}'],
        ]];
        return $spec;
    }

    private function enrichAuth(array $spec): array
    {
        if (!in_array('User', array_column($spec['models'], 'name'))) {
            $spec['models'][] = ['name' => 'User', 'fields' => [
                ['name' => 'id', 'type' => 'int'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'email', 'type' => 'string', 'attributes' => ['unique']],
                ['name' => 'password', 'type' => 'string'],
            ]];
        }
        if (!str_contains(json_encode($spec['pages']), 'Login')) {
            $spec['pages'][] = ['name' => 'Login', 'content' => [
                ['type' => 'say', 'value' => '<h1>Sign In</h1>'],
            ]];
            $spec['pages'][] = ['name' => 'Register', 'content' => [
                ['type' => 'say', 'value' => '<h1>Create Account</h1>'],
            ]];
        }
        $spec['apis'][] = ['method' => 'POST', 'path' => '/api/login', 'body' => [
            ['type' => 'return', 'value' => '{token: "login_token"}'],
        ]];
        $spec['apis'][] = ['method' => 'POST', 'path' => '/api/register', 'body' => [
            ['type' => 'return', 'value' => '{token: "register_token"}'],
        ]];
        return $spec;
    }

    private function enrichBlog(array $spec): array
    {
        $spec['models'][] = ['name' => 'Post', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'slug', 'type' => 'string', 'attributes' => ['unique']],
            ['name' => 'content', 'type' => 'text'],
            ['name' => 'excerpt', 'type' => 'text'],
            ['name' => 'published_at', 'type' => 'datetime', 'attributes' => ['nullable']],
            ['name' => 'user_id', 'type' => 'int'],
        ], 'relationships' => [
            ['name' => 'author', 'type' => 'belongsTo', 'target' => 'User'],
        ]];
        $spec['pages'][] = ['name' => 'Blog', 'content' => [
            ['type' => 'var', 'name' => 'title', 'value' => 'Blog'],
            ['type' => 'say', 'value' => "<h1 class='text-3xl font-bold'>{title}</h1>"],
        ]];
        $spec['pages'][] = ['name' => 'PostDetail', 'content' => [
            ['type' => 'say', 'value' => '<article>{post.content}</article>'],
        ]];
        $spec['apis'][] = ['method' => 'GET', 'path' => '/api/posts', 'body' => [
            ['type' => 'return', 'value' => 'Post::paginate(10)'],
        ]];
        return $spec;
    }

    private function enrichDocuments(array $spec): array
    {
        $spec['models'][] = ['name' => 'Document', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'file_path', 'type' => 'string'],
            ['name' => 'file_type', 'type' => 'string'],
            ['name' => 'file_size', 'type' => 'int'],
            ['name' => 'user_id', 'type' => 'int'],
        ], 'relationships' => [
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];
        $spec['apis'][] = ['method' => 'GET', 'path' => '/api/documents/{id}/download', 'body' => [
            ['type' => 'return', 'value' => '{status: "downloaded"}'],
        ]];
        $spec['pages'][] = ['name' => 'Documents', 'content' => [
            ['type' => 'var', 'name' => 'title', 'value' => 'Documents'],
            ['type' => 'say', 'value' => "<h1>{title}</h1>"],
        ]];
        return $spec;
    }

    private function enrichAgent(array $spec, string $desc): array
    {
        $agentName = 'SupportAgent';
        $agentPrompt = 'You are a helpful support assistant.';

        if (preg_match('/\b(real\s*estate|property)\b/', $desc)) {
            $agentName = 'PropertyAssistant';
            $agentPrompt = 'You are a knowledgeable real estate assistant.';
        } elseif (preg_match('/\b(ecommerce|shop|store)\b/', $desc)) {
            $agentName = 'ShoppingAssistant';
            $agentPrompt = 'You are a helpful shopping assistant.';
        } elseif (preg_match('/\b(crm|customer)\b/', $desc)) {
            $agentName = 'CRMAssistant';
            $agentPrompt = 'You are a CRM assistant helping with customer relations.';
        }

        $spec['agents'][] = [
            'name' => $agentName,
            'model' => 'gpt4',
            'prompt' => $agentPrompt,
            'tasks' => [
                ['name' => 'answerQuestion', 'params' => 'question', 'body' => [
                    'response = ask(question)',
                    'return response',
                ]],
                ['name' => 'processRequest', 'params' => 'request', 'body' => [
                    'result = analyze(request)',
                    'return result',
                ]],
            ],
        ];
        return $spec;
    }

    private function enrichPayments(array $spec): array
    {
        $spec['models'][] = ['name' => 'Payment', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'user_id', 'type' => 'int'],
            ['name' => 'amount', 'type' => 'float'],
            ['name' => 'currency', 'type' => 'string'],
            ['name' => 'status', 'type' => 'string'],
            ['name' => 'payment_method', 'type' => 'string'],
            ['name' => 'transaction_id', 'type' => 'string'],
        ], 'relationships' => [
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];
        $spec['models'][] = ['name' => 'Subscription', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'user_id', 'type' => 'int'],
            ['name' => 'plan', 'type' => 'string'],
            ['name' => 'status', 'type' => 'string'],
            ['name' => 'ends_at', 'type' => 'datetime'],
        ], 'relationships' => [
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];
        $spec['apis'][] = ['method' => 'POST', 'path' => '/api/payments', 'body' => [
            ['type' => 'return', 'value' => '{status: "processed"}'],
        ]];
        return $spec;
    }

    private function enrichNotifications(array $spec): array
    {
        $spec['models'][] = ['name' => 'Notification', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'user_id', 'type' => 'int'],
            ['name' => 'type', 'type' => 'string'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'body', 'type' => 'text'],
            ['name' => 'read_at', 'type' => 'datetime', 'attributes' => ['nullable']],
        ], 'relationships' => [
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];
        $spec['apis'][] = ['method' => 'GET', 'path' => '/api/notifications', 'body' => [
            ['type' => 'return', 'value' => '{notifications: []}'],
        ]];
        return $spec;
    }

    private function enrichSearch(array $spec, string $desc): array
    {
        $searchTarget = 'items';
        if (preg_match('/\b(properties|real\s*estate)\b/', $desc)) $searchTarget = 'properties';
        elseif (preg_match('/\b(products|shop)\b/', $desc)) $searchTarget = 'products';

        $spec['apis'][] = ['method' => 'GET', 'path' => "/api/search/{$searchTarget}", 'body' => [
            ['type' => 'return', 'value' => '{results: []}'],
        ]];
        $spec['pages'][] = ['name' => 'SearchResults', 'content' => [
            ['type' => 'var', 'name' => 'query', 'value' => ''],
            ['type' => 'say', 'value' => '<h1>Search Results for {query}</h1>'],
        ]];
        return $spec;
    }

    private function enrichReviews(array $spec): array
    {
        $spec['models'][] = ['name' => 'Review', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'user_id', 'type' => 'int'],
            ['name' => 'rating', 'type' => 'int'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'body', 'type' => 'text'],
            ['name' => 'reviewable_id', 'type' => 'int'],
            ['name' => 'reviewable_type', 'type' => 'string'],
        ], 'relationships' => [
            ['name' => 'user', 'type' => 'belongsTo', 'target' => 'User'],
        ]];
        $spec['apis'][] = ['method' => 'POST', 'path' => '/api/reviews', 'body' => [
            ['type' => 'return', 'value' => '{status: "created"}'],
        ]];
        return $spec;
    }

    private function enrichAnalytics(array $spec): array
    {
        $spec['pages'][] = ['name' => 'Analytics', 'content' => [
            ['type' => 'var', 'name' => 'title', 'value' => 'Analytics Dashboard'],
            ['type' => 'say', 'value' => "<h1>{title}</h1>"],
        ]];
        $spec['apis'][] = ['method' => 'GET', 'path' => '/api/analytics/summary', 'body' => [
            ['type' => 'return', 'value' => '{visitors: 0, pageviews: 0}'],
        ]];
        return $spec;
    }

    private function enrichWorkflows(array $spec, string $desc): array
    {
        $wfName = 'MainWorkflow';
        if (str_contains($desc, 'inquiry')) $wfName = 'InquiryWorkflow';
        elseif (str_contains($desc, 'order')) $wfName = 'OrderWorkflow';
        elseif (str_contains($desc, 'approval')) $wfName = 'ApprovalWorkflow';
        elseif (str_contains($desc, 'onboarding')) $wfName = 'OnboardingWorkflow';

        $spec['workflows'][] = [
            'name' => $wfName,
            'steps' => [
                ['name' => 'Initialize', 'body' => ["say \"Starting {$wfName}\""]],
                ['name' => 'Process', 'body' => ['say "Processing..."']],
                ['name' => 'Complete', 'body' => ['say "Workflow complete"']],
            ],
        ];
        return $spec;
    }

    private function enrichContacts(array $spec): array
    {
        $spec['models'][] = ['name' => 'Contact', 'fields' => [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'email', 'type' => 'string'],
            ['name' => 'phone', 'type' => 'string'],
            ['name' => 'subject', 'type' => 'string'],
            ['name' => 'message', 'type' => 'text'],
            ['name' => 'status', 'type' => 'string'],
        ]];
        $spec['apis'][] = ['method' => 'POST', 'path' => '/api/contact', 'body' => [
            ['type' => 'return', 'value' => '{status: "received"}'],
        ]];
        $spec['pages'][] = ['name' => 'Contact', 'content' => [
            ['type' => 'var', 'name' => 'title', 'value' => 'Contact Us'],
            ['type' => 'say', 'value' => "<h1>{title}</h1>"],
        ]];
        return $spec;
    }

    private function enrichExternalApis(array $spec, string $desc): array
    {
        $spec['workflows'][] = [
            'name' => 'IntegrationSync',
            'steps' => [
                ['name' => 'Fetch External Data', 'body' => ['say "Fetching data from external API"']],
                ['name' => 'Transform', 'body' => ['say "Transforming data to local format"']],
                ['name' => 'Sync', 'body' => ['say "Syncing to database"']],
            ],
        ];
        return $spec;
    }
}
