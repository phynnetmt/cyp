# CYP Self-Hosting Lexer
# Written in CYP for the bootstrap compiler

task tokenize(source)
    tokens = []
    pos = 0
    line = 1
    column = 1
    length = len(source)

    while pos < length
        ch = source[pos]

        # Newline
        if ch == "\n"
            tokens = append(tokens, {type: "NEWLINE", value: "\n", line: line, col: column})
            line = line + 1
            column = 1
            pos = pos + 1
            continue
        end

        # Carriage return
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

        # Whitespace
        if ch == " " or ch == "\t"
            pos = pos + 1
            column = column + 1
            continue
        end

        # Comments
        if ch == "#"
            comment = ""
            pos = pos + 1
            column = column + 1
            while pos < length and source[pos] != "\n"
                comment = comment + source[pos]
                pos = pos + 1
                column = column + 1
            end
            tokens = append(tokens, {type: "COMMENT", value: comment, line: line, col: column})
            continue
        end

        # Identifiers and keywords
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

        # Numbers
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

        # Strings
        if ch == '"' or ch == "'"
            quote = ch
            start = pos
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
                        error "Unterminated string escape"
                    end
                    esc = source[pos]
                    if esc == "n"; strValue = strValue + "\n"
                    elif esc == "t"; strValue = strValue + "\t"
                    elif esc == "r"; strValue = strValue + "\r"
                    elif esc == "\\"; strValue = strValue + "\\"
                    elif esc == '"'; strValue = strValue + '"'
                    elif esc == "'"; strValue = strValue + "'"
                    else; error "Invalid escape: " + esc
                    end
                    pos = pos + 1
                    column = column + 1
                    continue
                end
                if c == quote
                    pos = pos + 1
                    column = column + 1
                    tokens = append(tokens, {type: "STRING", value: strValue, line: line, col: startCol})
                    continue search
                end
                if c == "\n"
                    error "Unterminated string"
                end
                strValue = strValue + c
                pos = pos + 1
                column = column + 1
            end
            error "Unterminated string"
        end

        # Two-character tokens
        twoChar = ""
        if pos + 1 < length
            twoChar = source[pos] + source[pos + 1]
        end
        tt = twoCharToken(twoChar)
        if tt != ""
            tokens = append(tokens, {type: tt, value: twoChar, line: line, col: column})
            pos = pos + 2
            column = column + 2
            continue
        end

        # Single-character tokens
        if ch == "=";   tokens = append(tokens, {type: "=", value: "=", line: line, col: column})
        elif ch == ":"; tokens = append(tokens, {type: ":", value: ":", line: line, col: column})
        elif ch == ";"; tokens = append(tokens, {type: ";", value: ";", line: line, col: column})
        elif ch == ","; tokens = append(tokens, {type: ",", value: ",", line: line, col: column})
        elif ch == "."; tokens = append(tokens, {type: ".", value: ".", line: line, col: column})
        elif ch == "|"; tokens = append(tokens, {type: "|", value: "|", line: line, col: column})
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
        elif ch == "&"; tokens = append(tokens, {type: "&", value: "&", line: line, col: column})
        elif ch == "^"; tokens = append(tokens, {type: "^", value: "^", line: line, col: column})
        elif ch == "~"; tokens = append(tokens, {type: "~", value: "~", line: line, col: column})
        elif ch == "?"; tokens = append(tokens, {type: "?", value: "?", line: line, col: column})
        else
            error "Unexpected character: " + ch + " at " + line + ":" + column
        end

        pos = pos + 1
        column = column + 1

        label search
    end

    tokens = append(tokens, {type: "EOF", value: "", line: line, col: column})
    return filterTokens(tokens)
end

task isLetter(ch)
    return (ch >= "a" and ch <= "z") or (ch >= "A" and ch <= "Z")
end

task isDigit(ch)
    return ch >= "0" and ch <= "9"
end

task keyword(word)
    kw = match word
        "var" => "VAR"; "let" => "LET"; "task" => "TASK"; "func" => "FUNC"
        "if" => "IF"; "else" => "ELSE"; "elif" => "ELIF"
        "end" => "END"; "repeat" => "REPEAT"; "for" => "FOR"
        "in" => "IN"; "while" => "WHILE"; "return" => "RETURN"
        "model" => "MODEL"; "page" => "PAGE"; "api" => "API"
        "component" => "COMPONENT"; "say" => "SAY"
        "true" => "TRUE"; "false" => "FALSE"; "null" => "NULL"
        "and" => "AND"; "or" => "OR"; "not" => "NOT"
        "import" => "IMPORT"; "export" => "EXPORT"; "from" => "FROM"
        "class" => "CLASS"; "new" => "NEW"; "this" => "THIS"
        "extends" => "EXTENDS"; "implements" => "IMPLEMENTS"
        "interface" => "INTERFACE"; "enum" => "ENUM"
        "match" => "MATCH"; "try" => "TRY"; "catch" => "CATCH"
        "finally" => "FINALLY"; "throw" => "THROW"
        "async" => "ASYNC"; "await" => "AWAIT"
        "agent" => "AGENT"; "prompt" => "PROMPT"; "embed" => "EMBED"
        "public" => "PUBLIC"; "private" => "PRIVATE"
        "protected" => "PROTECTED"; "static" => "STATIC"
        "readonly" => "READONLY"; "type" => "TYPE"
        "record" => "RECORD"; "union" => "UNION"; "intersect" => "INTERSECT"
        "workflow" => "WORKFLOW"; "step" => "STEP"
    end
    return kw
end

task twoCharToken(str)
    tt = match str
        "==" => "=="; "!=" => "!="; "<=" => "<="; ">=" => ">="
        "&&" => "&&"; "||" => "||"; "=>" => "=>"; "::" => "::"
    end
    return tt
end

task filterTokens(tokens)
    filtered = []
    for t in tokens
        if t.type != "COMMENT"
            filtered = append(filtered, t)
        end
    end
    return filtered
end

task append(arr, item)
    arr[len(arr)] = item
    return arr
end

task substr(s, start, len)
    result = ""
    i = start
    while i < start + len and i < len(s)
        result = result + s[i]
        i = i + 1
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
