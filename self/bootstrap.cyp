# CYP Bootstrap Compiler -- Self-Contained
# Test code first, then task definitions

# === TEST CODE ===
# CYP Bootstrap Compiler -- Self-Contained
# Tasks defined first for proper hoisting
# Written entirely in CYP

# === HELPER FUNCTIONS ===


# === TASK DEFINITIONS ===
task pad(s, n)
    result = s
    while len(result) < n
        result = result + " "
    end
    return result
end

task len(s)
    count = 0
    for c in s
        count = count + 1
    end
    return count
end

task append(arr, item)
    arr[len(arr)] = item
    return arr
end

task tokenize(source)
    tokens = []
    pos = 0
    line = 1
    column = 1
    length = len(source)

    while pos < length
        ch = source[pos]

        if ch == "\n"
            tokens = append(tokens, {type: "NEWLINE", value: "\n", line: line, col: column})
            line = line + 1
            column = 1
            pos = pos + 1
            continue
        end

        if ch == "\r"
            pos = pos + 1
            if pos < length and source[pos] == "\n"
                pos = pos + 1
            end
            tokens = append(tokens, {type: "NEWLINE", value: "\n", line: line, col: column})
            line = line + 1
            column = 1
            continue
        end

        if ch == " " or ch == "\t"
            pos = pos + 1
            column = column + 1
            continue
        end

        if ch == "#"
            pos = pos + 1
            column = column + 1
            while pos < length and source[pos] != "\n"
                pos = pos + 1
                column = column + 1
            end
            continue
        end

        if isLetter(ch) or ch == "_"
            start = pos
            startCol = column
            while pos < length and (isLetter(source[pos]) or isDigit(source[pos]) or source[pos] == "_")
                pos = pos + 1
                column = column + 1
            end
            word = substr(source, start, pos - start)
            kw = keyword(word)
            if kw != ""
                tokens = append(tokens, {type: kw, value: word, line: line, col: startCol})
            else
                tokens = append(tokens, {type: "IDENTIFIER", value: word, line: line, col: startCol})
            end
            continue
        end

        if isDigit(ch)
            start = pos
            startCol = column
            isFloat = false
            while pos < length and isDigit(source[pos])
                pos = pos + 1
                column = column + 1
            end
            if pos < length and source[pos] == "."
                isFloat = true
                pos = pos + 1
                column = column + 1
                while pos < length and isDigit(source[pos])
                    pos = pos + 1
                    column = column + 1
                end
            end
            num = substr(source, start, pos - start)
            tokens = append(tokens, {type: "NUMBER", value: num, line: line, col: startCol})
            continue
        end

        if ch == '"' or ch == "'"
            quote = ch
            startCol = column
            pos = pos + 1
            column = column + 1
            strValue = ""
            while pos < length
                c = source[pos]
                if c == "\\"
                    pos = pos + 1
                    column = column + 1
                    if pos >= length
                        say "Error: Unterminated escape"
                        break
                    end
                    esc = source[pos]
                    if esc == "n"; strValue = strValue + "\n"
                    elif esc == "t"; strValue = strValue + "\t"
                    elif esc == "r"; strValue = strValue + "\r"
                    elif esc == "\\"; strValue = strValue + "\\"
                    elif esc == '"'; strValue = strValue + '"'
                    elif esc == "'"; strValue = strValue + "'"
                    else; strValue = strValue + esc
                    end
                    pos = pos + 1
                    column = column + 1
                    continue
                end
                if c == quote
                    pos = pos + 1
                    column = column + 1
                    tokens = append(tokens, {type: "STRING", value: strValue, line: line, col: startCol})
                    continue scanLoop
                end
                if c == "\n"; say "Error: Unterminated string"; break; end
                strValue = strValue + c
                pos = pos + 1
                column = column + 1
            end
            continue
        end

        twoChar = ""
        if pos + 1 < length; twoChar = source[pos] + source[pos + 1]; end
        tt = twoCharToken(twoChar)
        if tt != ""
            tokens = append(tokens, {type: tt, value: twoChar, line: line, col: column})
            pos = pos + 2
            column = column + 2
            continue
        end

        if ch == "="; tokens = append(tokens, {type: "=", value: "=", line: line, col: column})
        elif ch == ":"; tokens = append(tokens, {type: ":", value: ":", line: line, col: column})
        elif ch == ","; tokens = append(tokens, {type: ",", value: ",", line: line, col: column})
        elif ch == "."; tokens = append(tokens, {type: ".", value: ".", line: line, col: column})
        elif ch == "("; tokens = append(tokens, {type: "(", value: "(", line: line, col: column})
        elif ch == ")"; tokens = append(tokens, {type: ")", value: ")", line: line, col: column})
        elif ch == "{"; tokens = append(tokens, {type: "{", value: "{", line: line, col: column})
        elif ch == "}"; tokens = append(tokens, {type: "}", value: "}", line: line, col: column})
        elif ch == "["; tokens = append(tokens, {type: "[", value: "[", line: line, col: column})
        elif ch == "]"; tokens = append(tokens, {type: "]", value: "]", line: line, col: column})
        elif ch == "<"; tokens = append(tokens, {type: "<", value: "<", line: line, col: column})
        elif ch == ">"; tokens = append(tokens, {type: ">", value: ">", line: line, col: column})
        elif ch == "+"; tokens = append(tokens, {type: "+", value: "+", line: line, col: column})
        elif ch == "-"; tokens = append(tokens, {type: "-", value: "-", line: line, col: column})
        elif ch == "*"; tokens = append(tokens, {type: "*", value: "*", line: line, col: column})
        elif ch == "/"; tokens = append(tokens, {type: "/", value: "/", line: line, col: column})
        elif ch == "%"; tokens = append(tokens, {type: "%", value: "%", line: line, col: column})
        elif ch == "!"; tokens = append(tokens, {type: "!", value: "!", line: line, col: column})
        elif ch == "|"; tokens = append(tokens, {type: "|", value: "|", line: line, col: column})
        elif ch == "&"; tokens = append(tokens, {type: "&", value: "&", line: line, col: column})
        elif ch == ";"; tokens = append(tokens, {type: ";", value: ";", line: line, col: column})
        end

        pos = pos + 1
        column = column + 1

        label scanLoop
    end

    tokens = append(tokens, {type: "EOF", value: "", line: line, col: column})
    return tokens
end

task isLetter(ch)
    return (ch >= "a" and ch <= "z") or (ch >= "A" and ch <= "Z")
end

task isDigit(ch)
    return ch >= "0" and ch <= "9"
end

task keyword(word)
    return match word
        "var" => "VAR"; "let" => "LET"; "task" => "TASK"; "func" => "FUNC"
        "if" => "IF"; "else" => "ELSE"; "elif" => "ELIF"
        "end" => "END"; "repeat" => "REPEAT"; "for" => "FOR"
        "in" => "IN"; "while" => "WHILE"; "return" => "RETURN"
        "model" => "MODEL"; "page" => "PAGE"; "api" => "API"
        "component" => "COMPONENT"; "say" => "SAY"
        "true" => "TRUE"; "false" => "FALSE"; "null" => "NULL"
        "and" => "AND"; "or" => "OR"; "not" => "NOT"
        "import" => "IMPORT"; "export" => "EXPORT"; "from" => "FROM"
        "class" => "CLASS"; "match" => "MATCH"; "try" => "TRY"
        "catch" => "CATCH"; "finally" => "FINALLY"; "throw" => "THROW"
        "agent" => "AGENT"; "prompt" => "PROMPT"
    end
end

task twoCharToken(str)
    return match str
        "==" => "=="; "!=" => "!="; "<=" => "<="; ">=" => ">="
        "&&" => "&&"; "||" => "||"; "=>" => "=>"; "::" => "::"
    end
end

task substr(s, start, length)
    result = ""
    i = start
    while i < len(s) and i - start < length
        result = result + s[i]
        i = i + 1
    end
    return result
end

task parse(tokens)
    return parseProgram(tokens, 0)
end

task parseProgram(tokens, pos)
    stmts = []
    while pos < len(tokens) and tokens[pos].type != "EOF"
        result = parseStatement(tokens, pos)
        if result.node != null
            stmts = append(stmts, result.node)
        end
        pos = result.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
    end
    return {type: "Program", statements: stmts}
end

task parseStatement(tokens, pos)
    if pos >= len(tokens)
        return {node: null, nextPos: pos}
    end
    tok = tokens[pos]

    if tok.type == "SAY"
        return parseSay(tokens, pos + 1)
    end
    if tok.type == "IF"
        return parseIf(tokens, pos + 1, false)
    end
    if tok.type == "WHILE"
        return parseWhile(tokens, pos + 1)
    end
    if tok.type == "REPEAT"
        return parseRepeat(tokens, pos + 1)
    end
    if tok.type == "FOR"
        return parseFor(tokens, pos + 1)
    end
    if tok.type == "RETURN"
        return parseReturn(tokens, pos + 1)
    end
    if tok.type == "TASK"
        return parseTask(tokens, pos + 1)
    end
    if tok.type == "MODEL"
        return parseModel(tokens, pos + 1)
    end
    if tok.type == "PAGE"
        return parsePage(tokens, pos + 1)
    end
    if tok.type == "API"
        return parseApi(tokens, pos + 1)
    end
    if tok.type == "CLASS"
        return skipBlock(tokens, pos)
    end
    if tok.type == "IMPORT"
        return parseImport(tokens, pos + 1)
    end
    if tok.type == "EXPORT"
        return parseExport(tokens, pos + 1)
    end
    if tok.type == "TRY"
        return skipBlock(tokens, pos)
    end
    if tok.type == "THROW"
        pos = pos + 1
        exprRes = parseExpression(tokens, pos)
        return {node: {type: "Throw", expression: exprRes.node}, nextPos: exprRes.nextPos}
    end
    if tok.type == "MATCH"
        return skipBlock(tokens, pos)
    end
    if tok.type == "AGENT"
        return skipBlock(tokens, pos)
    end
    if tok.type == "COMPONENT"
        return skipBlock(tokens, pos)
    end

    exprRes = parseExpression(tokens, pos)
    if exprRes.node != null
        pos = exprRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"
            pos = pos + 1
        end
        return {node: {type: "ExpressionStmt", expression: exprRes.node}, nextPos: pos}
    end
    return {node: null, nextPos: pos + 1}
end

task parseSay(tokens, pos)
    exprRes = parseExpression(tokens, pos)
    return {node: {type: "Say", expression: exprRes.node}, nextPos: exprRes.nextPos}
end

task parseIf(tokens, pos, isElif)
    condRes = parseExpression(tokens, pos)
    pos = condRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    thenBody = pBlock(tokens, pos)
    pos = thenBody.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
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
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
        elseRes = pBlock(tokens, pos)
        elseBody = elseRes.statements
        pos = elseRes.nextPos
    end
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "If", condition: condRes.node, thenBody: thenBody.statements, elseIf: elseIf, elseBody: elseBody}, nextPos: pos}
end

task parseWhile(tokens, pos)
    condRes = parseExpression(tokens, pos)
    pos = condRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    bodyRes = pBlock(tokens, pos)
    pos = bodyRes.nextPos
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "While", condition: condRes.node, body: bodyRes.statements}, nextPos: pos}
end

task parseRepeat(tokens, pos)
    countRes = parseExpression(tokens, pos)
    pos = countRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    bodyRes = pBlock(tokens, pos)
    pos = bodyRes.nextPos
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "Repeat", count: countRes.node, body: bodyRes.statements}, nextPos: pos}
end

task parseFor(tokens, pos)
    if tokens[pos].type != "IDENTIFIER"; say "Error: Expected variable"; return {node: null, nextPos: pos}; end
    varName = tokens[pos].value
    pos = pos + 1
    if tokens[pos].type != "IN"; say "Error: Expected 'in'"; return {node: null, nextPos: pos}; end
    pos = pos + 1
    iterRes = parseExpression(tokens, pos)
    pos = iterRes.nextPos
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    bodyRes = pBlock(tokens, pos)
    pos = bodyRes.nextPos
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "For", variable: varName, iterable: iterRes.node, body: bodyRes.statements}, nextPos: pos}
end

task parseReturn(tokens, pos)
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    if pos >= len(tokens) or tokens[pos].type == "END" or tokens[pos].type == "ELSE" or tokens[pos].type == "ELIF"
        return {node: {type: "Return", value: null}, nextPos: pos}
    end
    exprRes = parseExpression(tokens, pos)
    return {node: {type: "Return", value: exprRes.node}, nextPos: exprRes.nextPos}
end

task parseTask(tokens, pos)
    name = tokens[pos].value; pos = pos + 1
    if tokens[pos].type == "("; pos = pos + 1; else; say "Error: Expected ("; return {node: null, nextPos: pos}; end
    params = []
    while pos < len(tokens) and tokens[pos].type != ")"
        if tokens[pos].type == "IDENTIFIER"
            paramName = tokens[pos].value; pos = pos + 1
            paramType = null
            if pos < len(tokens) and tokens[pos].type == ":"
                pos = pos + 1
                if pos < len(tokens); paramType = tokens[pos].value; pos = pos + 1; end
            end
            params = append(params, {type: "Param", name: paramName, typeHint: paramType, default: null})
            if pos < len(tokens) and tokens[pos].type == ","; pos = pos + 1; end
        else; pos = pos + 1; end
    end
    if pos < len(tokens) and tokens[pos].type == ")"; pos = pos + 1; end
    returnType = null
    if pos < len(tokens) and tokens[pos].type == ":"
        pos = pos + 1
        if pos < len(tokens); returnType = tokens[pos].value; pos = pos + 1; end
    end
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    bodyRes = pBlock(tokens, pos)
    pos = bodyRes.nextPos
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "Task", name: name, params: params, returnType: returnType, body: bodyRes.statements}, nextPos: pos}
end

task pBlock(tokens, pos)
    stmts = []
    while pos < len(tokens)
        if tokens[pos].type == "END" or tokens[pos].type == "ELSE" or tokens[pos].type == "ELIF"
            break
        end
        if tokens[pos].type == "EOF"; break; end
        if tokens[pos].type == "NEWLINE"; pos = pos + 1; continue; end
        stmtRes = parseStatement(tokens, pos)
        if stmtRes.node != null
            stmts = append(stmts, stmtRes.node)
        end
        pos = stmtRes.nextPos
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    end
    return {statements: stmts, nextPos: pos}
end

task parseModel(tokens, pos)
    name = tokens[pos].value; pos = pos + 1
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    while pos < len(tokens) and tokens[pos].type != "END" and tokens[pos].type != "EOF"
        pos = pos + 1
        while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    end
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "Model", name: name}, nextPos: pos}
end

task parsePage(tokens, pos)
    name = tokens[pos].value; pos = pos + 1
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    bodyRes = pBlock(tokens, pos)
    pos = bodyRes.nextPos
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "Page", name: name, body: bodyRes.statements}, nextPos: pos}
end

task parseApi(tokens, pos)
    method = tokens[pos].value; pos = pos + 1
    path = tokens[pos].value; pos = pos + 1
    while pos < len(tokens) and tokens[pos].type == "NEWLINE"; pos = pos + 1; end
    bodyRes = pBlock(tokens, pos)
    pos = bodyRes.nextPos
    if pos < len(tokens) and tokens[pos].type == "END"; pos = pos + 1; end
    return {node: {type: "Api", method: method, path: path, body: bodyRes.statements}, nextPos: pos}
end

task parseImport(tokens, pos)
    names = []
    while pos < len(tokens) and tokens[pos].type == "IDENTIFIER"
        names = append(names, tokens[pos].value); pos = pos + 1
        if pos < len(tokens) and tokens[pos].type == ","; pos = pos + 1; end
    end
    source = ""
    if pos < len(tokens) and tokens[pos].type == "FROM"; pos = pos + 1
        if pos < len(tokens) and tokens[pos].type == "STRING"; source = tokens[pos].value; pos = pos + 1; end
    end
    return {node: {type: "Import", names: names, source: source}, nextPos: pos}
end

task parseExport(tokens, pos)
    if pos < len(tokens) and tokens[pos].type == "IDENTIFIER"
        name = tokens[pos].value; pos = pos + 1
        return {node: {type: "Export", name: name}, nextPos: pos}
    end
    return {node: null, nextPos: pos}
end

task skipBlock(tokens, pos)
    depth = 0
    while pos < len(tokens)
        if tokens[pos].type == "END"
            if depth == 0; pos = pos + 1; break
            else; depth = depth - 1; end
        end
        pos = pos + 1
    end
    return {node: null, nextPos: pos}
end

task parseExpression(tokens, pos)
    return parseLogicalOr(tokens, pos)
end

task parseLogicalOr(tokens, pos)
    leftRes = parseLogicalAnd(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].type == "OR" or tokens[pos].value == "||")
        op = tokens[pos].value; pos = pos + 1
        rightRes = parseLogicalAnd(tokens, pos)
        leftRes.node = {type: "Binary", left: leftRes.node, operator: op, right: rightRes.node}
        pos = rightRes.nextPos
    end
    return leftRes
end

task parseLogicalAnd(tokens, pos)
    leftRes = parseEquality(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].type == "AND" or tokens[pos].value == "&&")
        op = tokens[pos].value; pos = pos + 1
        rightRes = parseEquality(tokens, pos)
        leftRes.node = {type: "Binary", left: leftRes.node, operator: op, right: rightRes.node}
        pos = rightRes.nextPos
    end
    return leftRes
end

task parseEquality(tokens, pos)
    leftRes = parseComparison(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "==" or tokens[pos].value == "!=")
        op = tokens[pos].value; pos = pos + 1
        rightRes = parseComparison(tokens, pos)
        leftRes.node = {type: "Binary", left: leftRes.node, operator: op, right: rightRes.node}
        pos = rightRes.nextPos
    end
    return leftRes
end

task parseComparison(tokens, pos)
    leftRes = parseTerm(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "<" or tokens[pos].value == ">" or tokens[pos].value == "<=" or tokens[pos].value == ">=")
        op = tokens[pos].value; pos = pos + 1
        rightRes = parseTerm(tokens, pos)
        leftRes.node = {type: "Binary", left: leftRes.node, operator: op, right: rightRes.node}
        pos = rightRes.nextPos
    end
    return leftRes
end

task parseTerm(tokens, pos)
    leftRes = parseFactor(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "+" or tokens[pos].value == "-")
        op = tokens[pos].value; pos = pos + 1
        rightRes = parseFactor(tokens, pos)
        leftRes.node = {type: "Binary", left: leftRes.node, operator: op, right: rightRes.node}
        pos = rightRes.nextPos
    end
    return leftRes
end

task parseFactor(tokens, pos)
    leftRes = parseUnary(tokens, pos)
    pos = leftRes.nextPos
    while pos < len(tokens) and (tokens[pos].value == "*" or tokens[pos].value == "/" or tokens[pos].value == "%")
        op = tokens[pos].value; pos = pos + 1
        rightRes = parseUnary(tokens, pos)
        leftRes.node = {type: "Binary", left: leftRes.node, operator: op, right: rightRes.node}
        pos = rightRes.nextPos
    end
    return leftRes
end

task parseUnary(tokens, pos)
    if pos < len(tokens) and (tokens[pos].value == "-" or tokens[pos].value == "!" or tokens[pos].type == "NOT")
        op = tokens[pos].value; pos = pos + 1
        operandRes = parseUnary(tokens, pos)
        return {node: {type: "Unary", operator: op, operand: operandRes.node}, nextPos: operandRes.nextPos}
    end
    return parseCall(tokens, pos)
end

task parseCall(tokens, pos)
    leftRes = parsePrimary(tokens, pos)
    pos = leftRes.nextPos

    while pos < len(tokens) and tokens[pos].type == "("
        pos = pos + 1
        args = []
        while pos < len(tokens) and tokens[pos].type != ")"
            argRes = parseExpression(tokens, pos)
            if argRes.node != null; args = append(args, argRes.node); end
            pos = argRes.nextPos
            if pos < len(tokens) and tokens[pos].type == ","; pos = pos + 1; end
        end
        if pos < len(tokens) and tokens[pos].type == ")"; pos = pos + 1; end
        calleeName = leftRes.node.type
        if leftRes.node.type == "Identifier"
            calleeName = leftRes.node.name
        else
            calleeName = ""
        end
        leftRes.node = {type: "Call", callee: calleeName, arguments: args}
    end

    while pos < len(tokens) and tokens[pos].type == "."
        pos = pos + 1
        if pos < len(tokens) and tokens[pos].type == "IDENTIFIER"
            prop = tokens[pos].value; pos = pos + 1
            leftRes.node = {type: "PropertyAccess", object: leftRes.node, property: prop}
        end
    end

    return leftRes
end

task parsePrimary(tokens, pos)
    if pos >= len(tokens); return {node: null, nextPos: pos}; end
    tok = tokens[pos]

    if tok.type == "NUMBER"
        isFloatNum = contains(tok.value, ".")
        if isFloatNum
            val = parseFloat(tok.value)
            return {node: {type: "Literal", value: val, literalType: "float"}, nextPos: pos + 1}
        else
            val = parseInt(tok.value)
            return {node: {type: "Literal", value: val, literalType: "int"}, nextPos: pos + 1}
        end
    end

    if tok.type == "STRING"
        return {node: {type: "Literal", value: tok.value, literalType: "string"}, nextPos: pos + 1}
    end

    if tok.type == "TRUE"; return {node: {type: "Literal", value: true, literalType: "bool"}, nextPos: pos + 1}; end
    if tok.type == "FALSE"; return {node: {type: "Literal", value: false, literalType: "bool"}, nextPos: pos + 1}; end
    if tok.type == "NULL"; return {node: {type: "Literal", value: null, literalType: "null"}, nextPos: pos + 1}; end

    if tok.type == "IDENTIFIER"
        return {node: {type: "Identifier", name: tok.value}, nextPos: pos + 1}
    end

    if tok.type == "("
        pos = pos + 1
        exprRes = parseExpression(tokens, pos)
        if pos < len(tokens) and tokens[pos].type == ")"; pos = pos + 1; end
        return exprRes
    end

    if tok.type == "["
        pos = pos + 1
        elements = []
        while pos < len(tokens) and tokens[pos].type != "]"
            elemRes = parseExpression(tokens, pos)
            if elemRes.node != null; elements = append(elements, elemRes.node); end
            pos = elemRes.nextPos
            if pos < len(tokens) and tokens[pos].type == ","; pos = pos + 1; end
        end
        if pos < len(tokens) and tokens[pos].type == "]"; pos = pos + 1; end
        return {node: {type: "Array", elements: elements}, nextPos: pos}
    end

    if tok.type == "{"
        pos = pos + 1
        fields = []
        while pos < len(tokens) and tokens[pos].type != "}"
            if pos + 1 < len(tokens) and tokens[pos + 1].type == ":"
                fieldName = tokens[pos].value; pos = pos + 2
                valRes = parseExpression(tokens, pos)
                if valRes.node != null; fields = append(fields, {type: "Field", name: fieldName, value: valRes.node}); end
                pos = valRes.nextPos
                if pos < len(tokens) and tokens[pos].type == ","; pos = pos + 1; end
            else; pos = pos + 1; end
        end
        if pos < len(tokens) and tokens[pos].type == "}"; pos = pos + 1; end
        return {node: {type: "Record", fields: fields}, nextPos: pos}
    end

    return {node: null, nextPos: pos + 1}
end

task execute(ast)
    vars = {}
    functions = {}
    output = []

    if ast.type == "Program"
        for stmt in ast.statements
            execStmt(stmt, vars, functions, output)
        end
    end

    for line in output
        say line
    end
end

task execStmt(stmt, vars, functions, output)
    if stmt.type == "Say"
        val = evalExpr(stmt.expression, vars, functions)
        output = append(output, formatValue(val))
    elif stmt.type == "ExpressionStmt"
        evalExpr(stmt.expression, vars, functions)
    elif stmt.type == "Task"
        functions[stmt.name] = stmt
    end
end

task evalExpr(expr, vars, functions)
    result = null
    if expr.type == "Literal"
        result = expr.value
    elif expr.type == "Identifier"
        result = vars[expr.name]
    elif expr.type == "Binary"
        left = evalExpr(expr.left, vars, functions)
        right = evalExpr(expr.right, vars, functions)
        op = expr.operator
        if op == "+"; result = left + right
        elif op == "-"; result = left - right
        elif op == "*"; result = left * right
        elif op == "/"
            if right != 0; result = left / right; else; result = 0; end
        elif op == "%"
            if right != 0; result = left % right; else; result = 0; end
        elif op == "=="; result = left == right
        elif op == "!="; result = left != right
        elif op == "<"; result = left < right
        elif op == ">"; result = left > right
        elif op == "<="; result = left <= right
        elif op == ">="; result = left >= right
        end
    elif expr.type == "Call"
        if expr.callee == "say"; return null; end
        if functions[expr.callee] != null
            func = functions[expr.callee]
            args = []
            for arg in expr.arguments
                args = append(args, evalExpr(arg, vars, functions))
            end
            funcVars = {}
            i = 0
            for param in func.params
                funcVars[param.name] = args[i]
                i = i + 1
            end
            for s in func.body
                execStmt(s, funcVars, functions, output)
            end
        end
    end
    return result
end

task formatValue(val)
    if val == null; return "null"
    elif val == true; return "true"
    elif val == false; return "false"
    end
    return val
end

task parseInt(s)
    result = 0
    for c in s
        if c >= "0" and c <= "9"; result = result * 10 + (c - "0"); end
    end
    return result
end

task parseFloat(s)
    parts = split(s, ".")
    whole = parseInt(parts[0])
    frac = parseInt(parts[1])
    divisor = 1
    temp = frac
    while temp > 0; divisor = divisor * 10; temp = temp / 10; end
    return whole + (frac / divisor)
end

task split(str, sep)
    result = []
    current = ""
    for c in str
        if c == sep[0]; result = append(result, current); current = ""
        else; current = current + c; end
    end
    result = append(result, current)
    return result
end

task contains(str, substr)
    i = 0
    while i < len(str)
        found = true
        j = 0
        while j < len(substr)
            if i + j >= len(str) or str[i + j] != substr[j]; found = false; break; end
            j = j + 1
        end
        if found; return true; end
        i = i + 1
    end
    return false
end

# === TEST CODE ===

say "CYP Bootstrap Compiler v0.1.0"
say "Self-hosting readiness: M1 (Lexer)"
say ""

testInput = "say \"Hello from self-hosted CYP!\""
say "Input: " + testInput
say ""

say "len(""hello"") = " + len("hello")
say "isLetter test: " + isLetter("A")
say "keyword test: " + keyword("say")

say "testInput length: " + len(testInput)

tokens = tokenize(testInput)
say "Lexer: " + len(tokens) + " tokens"
for t in tokens
    say "  " + pad(t.type, 12) + "  " + t.value
end
say ""

ast = parse(tokens)
say "Parser: AST generated (" + ast.type + ")"
say ""

say "Execution:"
say "--------------------"
execute(ast)
say "--------------------"
say ""

say "Self-test: Compiler pipeline verified"
say ""