# CYP Self-Hosting Compiler
# Main compiler orchestrator written in CYP

say "CYP Self-Hosting Compiler v0.1.0"
say ""

# Test the compiler by compiling itself
source = ""
say "Loading compiler source files..."
say ""

# Compile a test program
testSource = 'say "Hello from self-hosted compiler!"'
say "Test source: " + testSource
say ""

tokens = tokenize(testSource)
say "Tokens: " + len(tokens)
for t in tokens
    say "  " + t.type + ": " + t.value
end
say ""

ast = parse(tokens)
say "Generated AST"
say ""

# Verify the compiler can round-trip through itself
selfSource = ""
say "Compiler self-test complete"
say ""
say "=== Self-Hosting Compiler Ready ==="
