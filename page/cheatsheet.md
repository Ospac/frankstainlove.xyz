# Markdown 도우미

_이 파일은 lichen CMS에서 원본과 함께 보는 것을 전제로 합니다. 이 [링크](/cms/edit.php/page/cheatsheet.md)를 따라가거나 마우스 오른쪽 버튼을 클릭한 뒤 `소스 보기`를 선택하면 원본을 확인할 수 있습니다. 이 Markdown 문법의 자세한 내용은 [PHP Markdown](https://github.com/michelf/php-markdown?tab=readme-ov-file) 문서를 참고하세요._

## 제목

```markdown
# 제목 1단계

## 제목 2단계

### 제목 3단계
```

## 텍스트 서식

```markdown
_기울임꼴 텍스트_, **굵은 텍스트**, `코드 텍스트`
```

코드 텍스트는 백틱으로 감싸서 작성합니다. 일부 키보드에서는 백틱 키를 찾기 어려울 수 있으니 확인해 보세요.

## 인용문

```markdown
> 우리는 자본주의 안에서 살아간다. 자본주의의 힘은 벗어날 수 없을 것처럼 보인다. 왕의 신성한 권리도 그랬다. 인간의 힘이라면 무엇이든 인간의 저항과 노력으로 맞서고 바꿀 수 있다. 저항과 변화는 예술에서 시작되는 경우가 많고, 특히 우리의 예술, 즉 말의 예술에서 시작되는 경우가 많다.

Ursula K. Le Guin
```

## 번호 매기기 목록

```markdown
1. 첫번째 항목
2. 두번째 항목
3. 세번째 항목
```

## 글머리 기호 목록

```markdown
- 항목 하나
- 또 다른 항목
- 또 하나의 항목
```

## 가로줄

```markdown
---
```

## 링크

```markdown
[lichen의 원래 PHP 버전](https://lichen.sensorstation.co/unmaintained//)
```

## 로컬 이미지

```markdown
![여기에 이미지 대체 텍스트를 입력하세요](/assets/image/dandi.jpg)
```

## 외부 이미지

```markdown
![여기에 이미지 대체 텍스트를 입력하세요](https://cdn.dribbble.com/users/1570563/screenshots/5955986/dandelion_illustration_final_2_4x.jpg)
```

## 표

```markdown
| 문법     | 멋진가요? |
| -------- | ----- |
| Markdown | 네      |
| Gemini   | 네      |
```

## 코드 블록

~~~markdown
```ascii
       _                   _ _                _
 _   _| | ___ __ _   _  __| | |_   _ __   ___| |_
| | | | |/ / '__| | | |/ _` | __| | '_ \ / _ \ __|
| |_| |   <| |  | |_| | (_| | |_ _| | | |  __/ |_
 \__,_|_|\_\_|   \__,_|\__,_|\__(_)_| |_|\___|\__|
```
~~~

## 각주

```markdown
각주가 있는 문장입니다. [^1]

[^1]: 이것은 각주입니다.
```

## 코드 주석

```html
<!-- This is a comment -->
```

주석은 화면에 표시되지 않습니다. 페이지 원본을 편집하는 사람에게 메모를 남길 때 사용합니다.

## 제목 ID

```markdown
### ID가 있는 제목 {#cool-id}

[제목 ID로 연결되는 링크입니다](#cool-id)
```

## 정의 목록

```markdown
용어
: 정의
```

## 특수 문자 이스케이프

Markdown 기호를 문법이 아닌 일반 문자로 사용하려면 이스케이프해야 합니다. 이때 백슬래시를 사용합니다.

```markdown
\\ \# \* \_ \*\*
```

## HTML 삽입

아래와 같이 순수한 HTML을 Markdown 안에 삽입할 수 있습니다.

```html
<div style="background-color: #ff8800">
<p>주황색 배경의 텍스트</p>
</div>
```

HTML 안에 Markdown을 넣으려면 `markdown="1"` 속성을 지정하세요.

```html
<div style="background-color: #ff8800" markdown="1">
### 주황색 배경의 제목
</div>
```
