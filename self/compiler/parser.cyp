# CYP Self-Hosting Parser
# Written in CYP — Recursive descent parser

task parse(tokens)
    return parseProgram(tokens, 0)
end

task parseProgram(tokens, pos)
    stmts = []
    while pos < len(tokens) and tokens[pos].type != "EOF"
        result = parseStatement(tokens, pos)
        stmts = append(stmts, result.node)
        pos = result.nextPos
        # Skip newlines between statements
        while pos < len(tokens) and (tokens[pos].type == "NEWLINE" or tokens[pos].type == "COMMENT")
            pos = pos + 1
        end
    end
    return makeProgram(stmts)
end

task parseStatement(tokens, pos)
    if pos >= len(tokens)
        return {node: null, nextPos: pos}
    end

    tok = tokens[pos]
    pos = pos + 1

    result = match tok.type
        # End block markers should not be parsed as statements
        "END" => {node: null, nextPos: pos - 1}

        "SAY" => parseSay(tokens, pos)
        "IF" => parseIf(tokens, pos)
        "WHILE" => parseWhile(tokens, pos)
        "REPEAT" => parseRepeat(tokens, pos)
        "FOR" => parseFor(tokens, pos)
        "RETURN" => parseReturn(tokens, pos)
        "TASK" => parseTask(tokens, pos)
        "FUNC" => parseFunc(tokens, pos)
        "MODEL" => parseModel(tokens, pos)
        "PAGE" => parsePage(tokens, pos)
        "API" => parseApi(tokens, pos)
        "COMPONENT" => parseComponent(tokens, pos)
        "AGENT" => parseAgent(tokens, pos)
        "IMPORT" => parseImport(tokens, pos)
        "EXPORT" => parseExport(tokens, pos)
        "TRY" => parseTryCatch(tokens, pos)
        "THROW" => parseThrow(tokens, pos)
        "CLASS" => parseClass(tokens, pos)
        # Default: expression statement
        {node: null, nextPos: pos - 1}
    end

    # If the statement type wasn't matched, treat as expression
    if result.node == null
        # Re-parse as expression starting from the original token
        exprRes = parseExpression(tokens, pos - 1)
        # Expect newline or end after expression
        pos = exprRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
        return {node: makeExpressionStmt(exprRes.node, tok.line, tok.col), nextPos: pos}
    end

    return result
end

# --- Say ---
task parseSay(tokens, pos)
    exprRes = parseExpression(tokens, pos)
    pos = exprRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    return {node: makeSay(exprRes.node, 0, 0), nextPos: pos}
end

# --- If/Elif/Else ---
task parseIf(tokens, pos, isElif)
    condRes = parseExpression(tokens, pos)
    pos = condRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    thenBody = []
    while pos < len(tokens) and tokens[pos].type != "END"
        if tokens[pos].type == "ELSE" or tokens[pos].type == "ELIF"
            break
        end
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            thenBody = append(thenBody, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end

    elseIf = null
    elseBody = null

    if pos < len(tokens) and tokens[pos].type == "ELIF"
        pos = pos + 1
        elifRes = parseIf(tokens, pos, true)
        elseIf = elifRes.node
        pos = elifRes.nextPos
    end

    if pos < len(tokens) and tokens[pos].type == "ELSE"
        pos = pos + 1
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
        elseBody = []
        while pos < len(tokens) and tokens[pos].type != "END"
            stmtRes = parseStatement(tokens, pos)
            if stmtRes.node != null
                elseBody = append(elseBody, stmtRes.node)
            end
            pos = stmtRes.nextPos
            while pos < len(tokens) and tokens[pos].type == "NEWLINE"
                pos = pos + 1
            end
        end
    end

    # Consume 'end'
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end

    return {node: makeIf(condRes.node, thenBody, elseIf, elseBody, 0, 0), nextPos: pos}
end

# --- While ---
task parseWhile(tokens, pos)
    condRes = parseExpression(tokens, pos)
    pos = condRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    body = []
    while pos < len(tokens) and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            body = append(body, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeWhile(condRes.node, body, 0, 0), nextPos: pos}
end

# --- Repeat ---
task parseRepeat(tokens, pos)
    countRes = parseExpression(tokens, pos)
    pos = countRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    body = []
    while pos < len(tokens) and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            body = append(body, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeRepeat(countRes.node, body, 0, 0), nextPos: pos}
end

# --- For ---
task parseFor(tokens, pos)
    if pos >= len(tokens) or tokens[pos].type != "IDENTIFIER"
        error "Expected variable name in for loop"
    end
    varName = tokens[pos].value
    pos = pos + 1
    if pos >= len(tokens) or tokens[pos].type != "IN"
        error "Expected 'in' in for loop"
    end
    pos = pos + 1
    iterRes = parseExpression(tokens, pos)
    pos = iterRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    body = []
    while pos < len(tokens) and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            body = append(body, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeFor(varName, iterRes.node, body, 0, 0), nextPos: pos}
end

# --- Return ---
task parseReturn(tokens, pos)
    # Skip newlines
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    # If next token is 'end' or 'else'/'elif', return without value
    if pos >= len(tokens) or tokens[pos].type == "END" or tokens[pos].type == "ELSE" or tokens[pos].type == "ELIF"
        return {node: makeReturn(null, 0, 0), nextPos: pos}
    end
    exprRes = parseExpression(tokens, pos)
    return {node: makeReturn(exprRes.node, 0, 0), nextPos: exprRes.nextPos}
end

# --- Task ---
task parseTask(tokens, pos)
    if pos >= len(tokens) or tokens[pos].type != "IDENTIFIER"
        error "Expected task name"
    end
    name = tokens[pos].value
    pos = pos + 1
    if pos >= len(tokens) or tokens[pos].type != "("
        error "Expected '(' after task name"
    end
    pos = pos + 1
    params = []
    while pos < len(tokens) and tokens[pos].type != ")"
        if tokens[pos].type == "IDENTIFIER"
            paramName = tokens[pos].value
            pos = pos + 1
            paramType = null
            if pos < len(tokens) and tokens[pos].type == ":"
                pos = pos + 1
                if pos < len(tokens) and tokens[pos].type == "IDENTIFIER"
                    paramType = tokens[pos].value
                    pos = pos + 1
                end
            end
            params = append(params, makeParam(paramName, paramType, null))
            if pos < len(tokens) and tokens[pos].type == ","
                pos = pos + 1
            end
        else
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == ")"
        pos = pos + 1
    end
    returnType = null
    if pos < len(tokens) and tokens[pos].type == ":"
        pos = pos + 1
        if pos < len(tokens)
            returnType = tokens[pos].value
            pos = pos + 1
        end
    end
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    body = []
    while pos < len(tokens) and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            body = append(body, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeTask(name, params, returnType, body, 0, 0), nextPos: pos}
end

# --- Func (lambda) ---
task parseFunc(tokens, pos)
    if pos >= len(tokens) or tokens[pos].type != "("
        error "Expected '(' after func"
    end
    pos = pos + 1
    params = []
    while pos < len(tokens) and tokens[pos].type != ")"
        if tokens[pos].type == "IDENTIFIER"
            params = append(params, tokens[pos].value)
            pos = pos + 1
            if pos < len(tokens) and tokens[pos].type == ","
                pos = pos + 1
            end
        else
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == ")"
        pos = pos + 1
    end
    if pos >= len(tokens) or tokens[pos].type != "=>"
        error "Expected '=>' in func"
    end
    pos = pos + 1
    bodyRes = parseExpression(tokens, pos)
    return {node: makeFunc("", params, null, [makeReturn(bodyRes.node, 0, 0)], 0, 0), nextPos: bodyRes.nextPos}
end

# --- Expressions (simplified for bootstrapping) ---
task parseExpression(tokens, pos)
    return parseLogicalOr(tokens, pos)
end

task parseLogicalOr(tokens, pos)
    leftRes = parseLogicalAnd(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].type == "OR" or tokens[pos].value == "||")
        op = if tokens[pos].type == "OR" then "or" else "||"
        pos = pos + 1
        rightRes = parseLogicalAnd(tokens, pos)
        leftRes.node = makeBinary(leftRes.node, op, rightRes.node, 0, 0)
        pos = rightRes.nextPos
    end
    return {node: leftRes.node, nextPos: pos}
end

task parseLogicalAnd(tokens, pos)
    leftRes = parseEquality(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].type == "AND" or tokens[pos].value == "&&")
        op = if tokens[pos].type == "AND" then "and" else "&&"
        pos = pos + 1
        rightRes = parseEquality(tokens, pos)
        leftRes.node = makeBinary(leftRes.node, op, rightRes.node, 0, 0)
        pos = rightRes.nextPos
    end
    return {node: leftRes.node, nextPos: pos}
end

task parseEquality(tokens, pos)
    leftRes = parseComparison(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "==" or tokens[pos].value == "!=")
        op = tokens[pos].value
        pos = pos + 1
        rightRes = parseComparison(tokens, pos)
        leftRes.node = makeBinary(leftRes.node, op, rightRes.node, 0, 0)
        pos = rightRes.nextPos
    end
    return {node: leftRes.node, nextPos: pos}
end

task parseComparison(tokens, pos)
    leftRes = parseTerm(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "<" or tokens[pos].value == ">" or tokens[pos].value == "<=" or tokens[pos].value == ">=")
        op = tokens[pos].value
        pos = pos + 1
        rightRes = parseTerm(tokens, pos)
        leftRes.node = makeBinary(leftRes.node, op, rightRes.node, 0, 0)
        pos = rightRes.nextPos
    end
    return {node: leftRes.node, nextPos: pos}
end

task parseTerm(tokens, pos)
    leftRes = parseFactor(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "+" or tokens[pos].value == "-")
        op = tokens[pos].value
        pos = pos + 1
        rightRes = parseFactor(tokens, pos)
        leftRes.node = makeBinary(leftRes.node, op, rightRes.node, 0, 0)
        pos = rightRes.nextPos
    end
    return {node: leftRes.node, nextPos: pos}
end

task parseFactor(tokens, pos)
    leftRes = parseUnary(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "*" or tokens[pos].value == "/" or tokens[pos].value == "%")
        op = tokens[pos].value
        pos = pos + 1
        rightRes = parseUnary(tokens, pos)
        leftRes.node = makeBinary(leftRes.node, op, rightRes.node, 0, 0)
        pos = rightRes.nextPos
    end
    return {node: leftRes.node, nextPos: pos}
end

task parseUnary(tokens, pos)
    if pos < len(tokens) and (tokens[pos].value == "-" or tokens[pos].value == "!" or tokens[pos].type == "NOT")
        op = tokens[pos].value
        pos = pos + 1
        operandRes = parseUnary(tokens, pos)
        return {node: makeUnary(op, operandRes.node, 0, 0), nextPos: operandRes.nextPos}
    end
    return parseCall(tokens, pos)
end

task parseCall(tokens, pos)
    leftRes = parsePrimary(tokens, pos)
    if leftRes.node == null
        return {node: null, nextPos: pos}
    end
    pos = leftRes.nextPos

    # Handle function calls: ident(...)
    while pos < len(tokens) and tokens[pos].type == "("
        pos = pos + 1
        args = []
        while pos < len(tokens) and tokens[pos].type != ")"
            argRes = parseExpression(tokens, pos)
            if argRes.node != null
                args = append(args, argRes.node)
            end
            pos = argRes.nextPos
            if pos < len(tokens) and tokens[pos].type == ","
                pos = pos + 1
            end
        end
        if pos < len(tokens) and tokens[pos].type == ")"
            pos = pos + 1
        end
        # The callee is the leftRes.node which could be an identifier
        calleeName = if leftRes.node.type == "Identifier" then leftRes.node.name else ""
        leftRes.node = makeCall(calleeName, args, 0, 0)
    end

    # Property access: expr.identifier or expr.identifier()
    while pos < len(tokens) and tokens[pos].type == "."
        pos = pos + 1
        if pos < len(tokens) and tokens[pos].type == "IDENTIFIER"
            prop = tokens[pos].value
            pos = pos + 1
            if pos < len(tokens) and tokens[pos].type == "("
                # method call
                pos = pos + 1
                args = []
                while pos < len(tokens) and tokens[pos].type != ")"
                    argRes = parseExpression(tokens, pos)
                    if argRes.node != null
                        args = append(args, argRes.node)
                    end
                    pos = argRes.nextPos
                    if pos < len(tokens) and tokens[pos].type == ","
                        pos = pos + 1
                    end
                end
                if pos < len(tokens) and tokens[pos].type == ")"
                    pos = pos + 1
                end
                leftRes.node = makeCall(prop, args, 0, 0)
            else
                leftRes.node = makePropertyAccess(leftRes.node, prop, 0, 0)
            end
        end
    end

    # Index: expr[expr]
    while pos < len(tokens) and tokens[pos].type == "["
        pos = pos + 1
        indexRes = parseExpression(tokens, pos)
        pos = indexRes.nextPos
        if pos < len(tokens) and tokens[pos].type == "]"
            pos = pos + 1
        end
        leftRes.node = makeIndex(leftRes.node, indexRes.node, 0, 0)
    end

    return {node: leftRes.node, nextPos: pos}
end

task parsePrimary(tokens, pos)
    if pos >= len(tokens)
        return {node: null, nextPos: pos}
    end

    tok = tokens[pos]

    if tok.type == "NUMBER"
        isFloat = contains(tok.value, ".")
        val = if isFloat then parseFloat(tok.value) else parseInt(tok.value)
        return {node: makeLiteral(val, if isFloat then "float" else "int", tok.line, tok.col), nextPos: pos + 1}
    end

    if tok.type == "STRING"
        if contains(tok.value, "${")
            parts = parseInterpolation(tok.value)
            return {node: makeInterpolatedString(parts, tok.line, tok.col), nextPos: pos + 1}
        end
        return {node: makeLiteral(tok.value, "string", tok.line, tok.col), nextPos: pos + 1}
    end

    if tok.type == "TRUE"
        return {node: makeLiteral(true, "bool", tok.line, tok.col), nextPos: pos + 1}
    end
    if tok.type == "FALSE"
        return {node: makeLiteral(false, "bool", tok.line, tok.col), nextPos: pos + 1}
    end
    if tok.type == "NULL"
        return {node: makeLiteral(null, "null", tok.line, tok.col), nextPos: pos + 1}
    end

    if tok.type == "IDENTIFIER"
        return {node: makeIdentifier(tok.value, tok.line, tok.col), nextPos: pos + 1}
    end

    if tok.type == "("
        pos = pos + 1
        exprRes = parseExpression(tokens, pos)
        pos = exprRes.nextPos
        if pos < len(tokens) and tokens[pos].type == ")"
            pos = pos + 1
        end
        return {node: exprRes.node, nextPos: pos}
    end

    if tok.type == "["
        pos = pos + 1
        elements = []
        while pos < len(tokens) and tokens[pos].type != "]"
            elemRes = parseExpression(tokens, pos)
            if elemRes.node != null
                elements = append(elements, elemRes.node)
            end
            pos = elemRes.nextPos
            if pos < len(tokens) and tokens[pos].type == ","
                pos = pos + 1
            end
        end
        if pos < len(tokens) and tokens[pos].type == "]"
            pos = pos + 1
        end
        return {node: makeArray(elements, tok.line, tok.col), nextPos: pos}
    end

    if tok.type == "{"
        pos = pos + 1
        fields = []
        while pos < len(tokens) and tokens[pos].type != "}"
            if tokens[pos].type == "IDENTIFIER" and pos + 1 < len(tokens) and tokens[pos + 1].type == ":"
                fieldName = tokens[pos].value
                pos = pos + 2
                valRes = parseExpression(tokens, pos)
                if valRes.node != null
                    fields = append(fields, makeField(fieldName, valRes.node))
                end
                pos = valRes.nextPos
                if pos < len(tokens) and tokens[pos].type == ","
                    pos = pos + 1
                end
            else
                pos = pos + 1
            end
        end
        if pos < len(tokens) and tokens[pos].type == "}"
            pos = pos + 1
        end
        return {node: makeRecord(fields, tok.line, tok.col), nextPos: pos}
    end

    if tok.type == "SAY"
        pos = pos + 1
        exprRes = parseExpression(tokens, pos)
        return {node: makeSay(exprRes.node, tok.line, tok.col), nextPos: exprRes.nextPos}
    end

    return {node: null, nextPos: pos}
end

# --- Stub parsers for constructs not yet needed for bootstrapping ---
task parseModel(tokens, pos)
    if pos >= len(tokens) or tokens[pos].type != "IDENTIFIER"
        error "Expected model name"
    end
    name = tokens[pos].value
    pos = pos + 1
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    fields = []
    rels = []
    while pos < len(tokens) and tokens[pos].type != "END"
        if tokens[pos].type == "IDENTIFIER" and pos + 1 < len(tokens) and tokens[pos + 1].type == ":"
            fieldName = tokens[pos].value
            pos = pos + 2
            fieldType = if pos < len(tokens) then tokens[pos].value else "string"
            pos = pos + 1
            attrs = []
            while pos < len(tokens) and (tokens[pos].type == "IDENTIFIER" or tokens[pos].type == "UNIQUE" or tokens[pos].type == "NULLABLE")
                attrs = append(attrs, tokens[pos].value)
                pos = pos + 1
            end
            fields = append(fields, makeModelField(fieldName, fieldType, attrs))
        elif tokens[pos].type == "IDENTIFIER" and pos + 2 < len(tokens)
            relName = tokens[pos].value
            pos = pos + 1
            relType = tokens[pos].value
            pos = pos + 1
            relTarget = tokens[pos].value
            pos = pos + 1
            rels = append(rels, makeModelRelationship(relName, relType, relTarget))
        else
            pos = pos + 1
        end
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeModel(name, fields, rels, 0, 0), nextPos: pos}
end

task parsePage(tokens, pos)
    if pos >= len(tokens) or tokens[pos].type != "IDENTIFIER"
        error "Expected page name"
    end
    name = tokens[pos].value
    pos = pos + 1
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    body = []
    while pos < len(tokens) and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            body = append(body, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makePage(name, body, 0, 0), nextPos: pos}
end

task parseApi(tokens, pos)
    if pos >= len(tokens)
        error "Expected HTTP method"
    end
    method = tokens[pos].value
    pos = pos + 1
    if pos >= len(tokens) or tokens[pos].type != "STRING"
        error "Expected path string"
    end
    path = tokens[pos].value
    pos = pos + 1
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    body = []
    while pos < len(tokens) and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            body = append(body, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeApi(method, path, body, 0, 0), nextPos: pos}
end

task parseComponent(tokens, pos)
    if pos >= len(tokens) or tokens[pos].type != "IDENTIFIER"
        error "Expected component name"
    end
    name = tokens[pos].value
    pos = pos + 1
    props = []
    if pos < len(tokens) and tokens[pos].type == "("
        pos = pos + 1
        while pos < len(tokens) and tokens[pos].type != ")"
            if tokens[pos].type == "IDENTIFIER"
                props = append(props, tokens[pos].value)
            end
            pos = pos + 1
            if pos < len(tokens) and tokens[pos].type == ","
                pos = pos + 1
            end
        end
        if pos < len(tokens) and tokens[pos].type == ")"
            pos = pos + 1
        end
    end
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    body = []
    while pos < len(tokens) and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            body = append(body, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeComponent(name, props, body, 0, 0), nextPos: pos}
end

task parseAgent(tokens, pos)
    if pos >= len(tokens) or tokens[pos].type != "IDENTIFIER"
        error "Expected agent name"
    end
    name = tokens[pos].value
    pos = pos + 1
    model = ""
    if pos < len(tokens) and tokens[pos].type == ":"
        pos = pos + 1
        if pos < len(tokens)
            model = tokens[pos].value
            pos = pos + 1
        end
    end
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    prompt = null
    tasks = []
    while pos < len(tokens) and tokens[pos].type != "END"
        if tokens[pos].type == "PROMPT"
            pos = pos + 1
            if pos < len(tokens) and tokens[pos].type == "STRING"
                prompt = tokens[pos].value
                pos = pos + 1
            end
        elif tokens[pos].type == "TASK"
            pos = pos + 1
            taskName = if pos < len(tokens) then tokens[pos].value else "unknown"
            pos = pos + 1
            tasks = append(tasks, taskName)
        else
            pos = pos + 1
        end
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeAgent(name, model, prompt, tasks, 0, 0), nextPos: pos}
end

task parseImport(tokens, pos)
    names = []
    while pos < len(tokens) and tokens[pos].type == "IDENTIFIER"
        names = append(names, tokens[pos].value)
        pos = pos + 1
        if pos < len(tokens) and tokens[pos].type == ","
            pos = pos + 1
        end
    end
    source = ""
    if pos < len(tokens) and tokens[pos].type == "FROM"
        pos = pos + 1
        if pos < len(tokens) and tokens[pos].type == "STRING"
            source = tokens[pos].value
            pos = pos + 1
        end
    end
    return {node: makeImport(names, source, 0, 0), nextPos: pos}
end

task parseExport(tokens, pos)
    if pos < len(tokens) and tokens[pos].type == "IDENTIFIER"
        name = tokens[pos].value
        pos = pos + 1
        return {node: makeExport(name, 0, 0), nextPos: pos}
    end
    return {node: null, nextPos: pos}
end

task parseTryCatch(tokens, pos)
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"
        pos = pos + 1
    end
    tryBody = []
    while pos < len(tokens) and tokens[pos].type != "CATCH" and tokens[pos].type != "FINALLY" and tokens[pos].type != "END"
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            tryBody = append(tryBody, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    catchVar = null
    catchBody = null
    if pos < len(tokens) and tokens[pos].type == "CATCH"
        pos = pos + 1
        if pos < len(tokens) and tokens[pos].type == "|"
            pos = pos + 1
            if pos < len(tokens)
                catchVar = tokens[pos].value
                pos = pos + 1
            end
            if pos < len(tokens) and tokens[pos].type == "|"
                pos = pos + 1
            end
        end
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
        catchBody = []
        while pos < len(tokens) and tokens[pos].type != "FINALLY" and tokens[pos].type != "END"
            stmtRes = parseStatement(tokens, pos)
            if stmtRes.node != null
                catchBody = append(catchBody, stmtRes.node)
            end
            pos = stmtRes.nextPos
            while pos < len(tokens) and tokens[pos].type == "NEWLINE"
                pos = pos + 1
            end
        end
    end
    finallyBody = null
    if pos < len(tokens) and tokens[pos].type == "FINALLY"
        pos = pos + 1
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
        finallyBody = []
        while pos < len(tokens) and tokens[pos].type != "END"
            stmtRes = parseStatement(tokens, pos)
            if stmtRes.node != null
                finallyBody = append(finallyBody, stmtRes.node)
            end
            pos = stmtRes.nextPos
            while pos < len(tokens) and tokens[pos].type == "NEWLINE"
                pos = pos + 1
            end
        end
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: makeTryCatch(tryBody, catchVar, catchBody, finallyBody, 0, 0), nextPos: pos}
end

task parseThrow(tokens, pos)
    exprRes = parseExpression(tokens, pos)
    return {node: makeThrow(exprRes.node, 0, 0), nextPos: exprRes.nextPos}
end

task parseClass(tokens, pos)
    name = if pos < len(tokens) then tokens[pos].value else "Unknown"
    pos = pos + 1
    while pos < len(tokens) and tokens[pos].type != "END"
        pos = pos + 1
    end
    if pos < len(tokens) and tokens[pos].type == "END"
        pos = pos + 1
    end
    return {node: {type: "Class", name: name, line: 0, col: 0}, nextPos: pos}
end

# --- Helper tasks ---
task makeExpressionStmt(expr, line, col)
    return {type: "ExpressionStmt", expression: expr, line: line, col: col}
end

task parseInterpolation(raw)
    parts = []
    pos = 0
    remaining = raw
    while true
        start = indexOf(remaining, "${", pos)
        if start < 0
            break
        end
        if start > pos
            parts = append(parts, makeStringPart(false, substr2(remaining, pos, start - pos), null))
        end
        endPos = indexOf(remaining, "}", start + 2)
        if endPos < 0
            parts = append(parts, makeStringPart(false, substr2(remaining, start), null))
            break
        end
        varName = substr2(remaining, start + 2, endPos - start - 2)
        parts = append(parts, makeStringPart(true, varName, makeIdentifier(varName, 0, 0)))
        pos = endPos + 1
    end
    if pos < len2(remaining)
        parts = append(parts, makeStringPart(false, substr2(remaining, pos), null))
    end
    if len2(parts) == 0
        parts = append(parts, makeStringPart(false, raw, null))
    end
    return parts
end

task parseInt(s)
    result = 0
    for c in s
        if c >= "0" and c <= "9"
            result = result * 10 + (c - "0")
        end
    end
    return result
end

task parseFloat(s)
    parts = split(s, ".")
    whole = parseInt(parts[0])
    frac = parseInt(parts[1])
    divisor = 1
    f = frac
    while f > 0
        divisor = divisor * 10
        f = f / 10
    end
    return whole + (frac / divisor)
end

task contains(str, substr)
    return indexOf(str, substr, 0) >= 0
end

task indexOf(str, search, start)
    i = start
    while i < len2(str)
        found = true
        j = 0
        while j < len2(search)
            if i + j >= len2(str) or str[i + j] != search[j]
                found = false
                break
            end
            j = j + 1
        end
        if found
            return i
        end
        i = i + 1
    end
    return -1
end

task split(str, delim)
    result = []
    current = ""
    for c in str
        if c == delim[0]
            result = append(result, current)
            current = ""
        else
            current = current + c
        end
    end
    result = append(result, current)
    return result
end

task substr2(str, start, len)
    result = ""
    i = start
    while i < len2(str) and (len < 0 or i - start < len)
        result = result + str[i]
        i = i + 1
    end
    return result
end

task len2(str)
    count = 0
    for c in str
        count = count + 1
    end
    return count
end

task append(arr, item)
    arr[len(arr)] = item
    return arr
end

task len(arr)
    count = 0
    for i in arr
        count = count + 1
    end
    return count
end
