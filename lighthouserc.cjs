module.exports = {
    ci: {
        collect: {
            url: [
                'http://127.0.0.1:8000/',
                'http://127.0.0.1:8000/venues',
                'http://127.0.0.1:8000/venue/antic-futsal-center-contoh',
            ],
            numberOfRuns: 1,
            settings: {
                formFactor: 'mobile',
                screenEmulation: {
                    mobile: true,
                    width: 412,
                    height: 823,
                    deviceScaleFactor: 1.75,
                    disabled: false,
                },
            },
        },
        assert: {
            assertions: {
                'categories:performance': ['error', { minScore: 0.95 }],
                'categories:accessibility': ['error', { minScore: 1.0 }],
                'categories:best-practices': ['error', { minScore: 1.0 }],
                'categories:seo': ['error', { minScore: 1.0 }],
            },
        },
        upload: {
            target: 'filesystem',
            outputDir: '.lighthouseci/mobile',
        },
    },
};
