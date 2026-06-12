# CYP Standard Library — String Module
# Written in CYP

task len(str)
    count = 0
    for c in str
        count = count + 1
    end
    return count
end

task substring(str, start, length)
    result = ""
    i = start
    while i < len(str) and (length < 0 or i - start < length)
        result = result + str[i]
        i = i + 1
    end
    return result
end

task contains(str, substr)
    return indexOf(str, substr, 0) >= 0
end

task indexOf(str, search, start)
    i = start
    while i < len(str)
        found = true
        j = 0
        while j < len(search)
            if i + j >= len(str) or str[i + j] != search[j]
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

task startsWith(str, prefix)
    if len(str) < len(prefix)
        return false
    end
    i = 0
    while i < len(prefix)
        if str[i] != prefix[i]
            return false
        end
        i = i + 1
    end
    return true
end

task endsWith(str, suffix)
    if len(str) < len(suffix)
        return false
    end
    start = len(str) - len(suffix)
    i = 0
    while i < len(suffix)
        if str[start + i] != suffix[i]
            return false
        end
        i = i + 1
    end
    return true
end

task toUpper(str)
    result = ""
    for c in str
        if c >= "a" and c <= "z"
            result = result + chr(ord(c) - 32)
        else
            result = result + c
        end
    end
    return result
end

task toLower(str)
    result = ""
    for c in str
        if c >= "A" and c <= "Z"
            result = result + chr(ord(c) + 32)
        else
            result = result + c
        end
    end
    return result
end

task trim(str)
    return trimEnd(trimStart(str))
end

task trimStart(str)
    i = 0
    while i < len(str) and (str[i] == " " or str[i] == "\t" or str[i] == "\n")
        i = i + 1
    end
    return substring(str, i, len(str) - i)
end

task trimEnd(str)
    i = len(str) - 1
    while i >= 0 and (str[i] == " " or str[i] == "\t" or str[i] == "\n")
        i = i - 1
    end
    return substring(str, 0, i + 1)
end

task split(str, sep)
    result = []
    current = ""
    for c in str
        if c == sep[0]
            result = append(result, current)
            current = ""
        else
            current = current + c
        end
    end
    result = append(result, current)
    return result
end

task join(arr, sep)
    result = ""
    first = true
    for item in arr
        if not first
            result = result + sep
        end
        result = result + item
        first = false
    end
    return result
end

task ord(c)
    # Simplified ASCII code
    if c >= "0" and c <= "9"; return c - "0" + 48
    elif c >= "A" and c <= "Z"; return c - "A" + 65
    elif c >= "a" and c <= "z"; return c - "a" + 97
    elif c == " "; return 32
    elif c == "\n"; return 10
    elif c == "\t"; return 9
    elif c == "!"; return 33; elif c == '"'; return 34
    elif c == "#"; return 35; elif c == "$"; return 36
    elif c == "%"; return 37; elif c == "&"; return 38
    elif c == "'"; return 39; elif c == "("; return 40
    elif c == ")"; return 41; elif c == "*"; return 42
    elif c == "+"; return 43; elif c == ","; return 44
    elif c == "-"; return 45; elif c == "."; return 46
    elif c == "/"; return 47; elif c == ":"; return 58
    elif c == ";"; return 59; elif c == "<"; return 60
    elif c == "="; return 61; elif c == ">"; return 62
    elif c == "?"; return 63; elif c == "@"; return 64
    elif c == "["; return 91; elif c == "\\"; return 92
    elif c == "]"; return 93; elif c == "^"; return 94
    elif c == "_"; return 95; elif c == "`"; return 96
    elif c == "{"; return 123; elif c == "|"; return 124
    elif c == "}"; return 125; elif c == "~"; return 126
    else; return 0
    end
end

task chr(code)
    # Simplified ASCII to character
    return "?"
end

task append(arr, item)
    arr[len(arr)] = item
    return arr
end
